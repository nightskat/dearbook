<?php

declare(strict_types = 1);

namespace App\Services;

use App\Data\ChildrenAwareData;
use App\Data\GenerationDataAdvanced;
use App\Data\GenerationDataSimple;
use App\Data\Storyline;
use App\Data\StorylineData;
use App\Enums\BookState;
use App\Enums\GenerationType;
use App\Exceptions\InvalidDataGeneratedByOllama;
use App\Exceptions\UnsafeForChildrenException;
use App\Http\Requests\CreateBookAdvancedRequest;
use App\Http\Requests\CreateBookRequest;
use App\Http\Requests\StoreAssetsRequest;
use App\Http\Requests\StoreSpeechRequest;
use App\Models\Book;
use App\Services\Traits\Resolvable;
use Exception;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Locale;
use PrinsFrank\Standards\Language\LanguageAlpha2;
use Throwable;

class BookService
{
    use Resolvable;

    private int $tries = 5;

    public function __construct(
        private readonly OllamaService $ollama,
    )
    {
    }

    public function allByWallet(string $wallet): Collection
    {
        return Book::query()
            ->where('wallet', $wallet)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @throws Throwable
     * @throws Exception
     * @throws ConnectionException
     */
    public function generateStoryline(Book $book): Storyline
    {
        $generationData = match ($book->generation_type) {
            GenerationType::Simple => GenerationDataSimple::from($book->generation_data),
            GenerationType::Advanced => GenerationDataAdvanced::from($book->generation_data),
        };

        $childrenAwareData = $this->isSafeForChildren($generationData);

        if ($childrenAwareData->isSafe === false) {
            throw new UnsafeForChildrenException($childrenAwareData->reason);
        }

        return retry(3, function () use ($book, $generationData) {

            $language = $this->extractPromptLanguage($generationData->prompt);

            return new Storyline(
                language: $language,
                data: $book = $this->generateBookMainStoryLine($language, $generationData),
                illustrations: $this->generateIllustrationDirectionFromParagraphs($book->paragraphs, $generationData),
            );

        });
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws ConnectionException
     */
    private function isSafeForChildren(GenerationDataSimple|GenerationDataAdvanced $data): ChildrenAwareData
    {
        return ChildrenAwareData::from(
            $this->ollama->generateJsonSchema(
                ...$this->askIfPromptIsSafeForChildren($data),
            ),
        );
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws ConnectionException
     */
    private function extractPromptLanguage(string $prompt): LanguageAlpha2
    {
        $response = $this->ollama->generateJsonSchema(
            ...$this->askWhatLanguagePromptWasWritten($prompt),
        );

        return LanguageAlpha2::tryFrom(
            Locale::getPrimaryLanguage($response->get('language')),
        );
    }

    private function askWhatLanguagePromptWasWritten(string $prompt): array
    {
        $schema = [
            'type' => 'object',
            'required' => [ 'language' ],
            'properties' => [
                'language' => [
                    'type' => 'string',
                ],
            ],
        ];

        $prompt = <<<PROMPT
        Analyze the following user input and identify the language it was written in.
        Do not attempt to understand the meaning of the content; simply return the language it is written in.

        ----- start_of_user_input_content
        $prompt
        ----- end_of_user_input_content

        Respond with the ISO639-1 language code using JSON.
        PROMPT;

        return [ $prompt, $schema ];
    }

    private function askIfPromptIsSafeForChildren(GenerationDataSimple|GenerationDataAdvanced $data): array
    {
        $schema = [
            'type' => 'object',
            'required' => [ 'isSafe', 'reason' ],
            'properties' => [
                'isSafe' => [
                    'type' => 'boolean',
                ],
                'reason' => [
                    'type' => 'string',
                ],
            ],
        ];

        $input = match ($data instanceof GenerationDataSimple) {
            true => $data->prompt,
            false => "$data->prompt, $data->title",
        };

        $prompt = <<<PROMPT
        Analyze the following user input prompt and flag it as unsafe if it contains any language related to pornography, sex, or drugs.

        ----- start_of_user_input_content
        $input
        ----- end_of_user_input_content

        Respond using JSON
        PROMPT;

        return [ $prompt, $schema ];
    }

    public function pendingBooks(): Collection
    {
        Book::query()
            ->whereIn('state', [
                BookState::PendingStoryLine->value,
                BookState::PendingIllustrations->value,
            ])
            ->where('fetched_at', '<', now()->subMinutes(30))
            ->update([
                'fetched_at' => null,
            ]);

        return Book::query()
            ->where('state', BookState::PendingStoryLine)
            ->whereNull('fetched_at')
            ->orderBy('created_at')
            ->get();
    }

    public function markBookAsFailed(Book $book, string $reason): void
    {
        $book->reason = $reason;
        $book->state = BookState::Failed;
        $book->save();
    }

    public function updateStoryline(Book $book, Storyline $storyline): bool
    {
        $book->title = $storyline->data->title;
        $book->language = $storyline->language;
        $book->synopsis = $storyline->data->synopsis;
        $book->paragraphs = $storyline->data->paragraphs;
        $book->illustrations = $storyline->illustrations;
        $book->state = BookState::PendingIllustrations;
        $book->fetched_at = null;

        return $book->save();
    }

    public function storeAssets(Book $book, StoreAssetsRequest $request): bool
    {
        $book->assets = collect($request->allFiles())->mapWithKeys(fn (UploadedFile $file, string $name) => [
            $name => $file->store(options: [ 'disk' => 'public' ]),
        ]);

        $book->state = BookState::Completed;
        $book->fetched_at = null;

        return $book->save();
    }

    public function storeSpeech(Book $book, StoreSpeechRequest $request): bool
    {
        $book->speech = collect($request->file('assets'))->mapWithKeys(fn (UploadedFile $file, string $name) => [
            $name => $file->store(options: [ 'disk' => 'public' ]),
        ]);

        return $book->save();
    }

    public function createPendingBook(CreateBookRequest $request): Book
    {
        $book = new Book();
        $book->wallet = $request->wallet;
        $book->generation_type = GenerationType::Simple;
        $book->generation_data = GenerationDataSimple::from($request->toArray());

        $book->save();

        return $book;
    }

    public function createPendingBookAdvanced(CreateBookAdvancedRequest $request): Book
    {
        $book = new Book();

        $book->wallet = $request->wallet;
        $book->generation_type = GenerationType::Advanced;
        $book->generation_data = GenerationDataAdvanced::from($request->toArray());

        $book->save();

        return $book;
    }

    public function getPendingBook(): ?Book
    {
        $book = Book::query()
            ->where('state', BookState::PendingIllustrations)
            ->where('fetched_at', null)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->first();

        if ($book) {
            $book->touch('fetched_at');
        }

        return $book;
    }

    public function getRandomBooks(): Paginator
    {
        return cache()->flexible('books', [ 5, 10 ], function () {

            return Book::query()
                ->where('state', BookState::Completed)
                ->inRandomOrder()
                ->simplePaginate(12);

        });
    }

    public function findManyByBatchIds(array $ids): EloquentCollection
    {
        return Book::query()
            ->whereIn('id', $ids)
            ->where('state', BookState::Completed)
            ->oldest()
            ->limit(10)
            ->get();
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @throws ConnectionException
     */
    private function generateIllustrationDirectionFromParagraphs(array $paragraphs, GenerationDataAdvanced|GenerationDataSimple $data): array
    {
        $illustrations = OllamaService::resolve()
            ->pool($this->describeIllustrationPrompt($paragraphs, $data))
            ->map(fn (Collection $response) => $response->get('illustration'));

        if (count($illustrations) !== count($paragraphs)) {
            throw new Exception('Generated illustration prompt is not valid...');
        }

        return $illustrations->toArray();
    }

    /**
     * @throws Throwable
     * @throws Exception
     * @throws ConnectionException
     */
    private function generateBookMainStoryLine(LanguageAlpha2 $language, GenerationDataAdvanced|GenerationDataSimple $data): StorylineData
    {
        $data = StorylineData::from(
            $this->ollama->generateJsonSchema(
                ...$this->generateStoryFromPrompt($language, $data),
            ),
        );

        if ($data->isValid() === false) {
            throw new InvalidDataGeneratedByOllama();
        }

        return $data;
    }

    private function generateStoryFromPrompt(LanguageAlpha2 $language, GenerationDataAdvanced|GenerationDataSimple $data): array
    {
        $schema = [
            'type' => 'object',
            'required' => [ 'title', 'synopsis', 'paragraphs' ],
            'properties' => [
                'title' => [
                    'type' => 'string',
                ],
                'synopsis' => [
                    'type' => 'string',
                    'minLength' => 100,
                ],
                'paragraphs' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ];

        $title = match ($data instanceof GenerationDataAdvanced) {
            true => "Book title: '$data->title'",
            false => '',
        };

        $exclusion = match ($data instanceof GenerationDataAdvanced) {
            true => "Ensure the story does not make any mention or include these elements: '$data->negative'",
            false => '',
        };

        $prompt = <<<PROMPT
        You are a children book writer,
        your sole purpose is to create engaging and fun stories for kids.
        Write a humorous and engaging children's book with exactly 10 paragraphs,
        each paragraph should be written in a simple and playful tone suitable for children,
        and needs to be very short in length, have it written with language that’s easy to understand
        and captivating for young children.

        Use the following user-provided input as the theme or storyline, but ensure you follow the specified rules:

        $title

        ----- start_of_user_input_content
        $data->prompt
        ----- end_of_user_input_content

        $exclusion

        Hard Rules:
         - The story must be written exclusively in "$language->name."
         - Avoid mixing any other language or text within the output.
         - Maintain consistency in the specified language throughout.
         - Don't wrap each paragraph in any additional symbols like {} or "".
         - Ensure the synopsis has at least 100 characters.

        Respond using JSON
        PROMPT;

        return [ $prompt, $schema ];
    }

    private function describeIllustrationPrompt(array $paragraphs, GenerationDataAdvanced|GenerationDataSimple $data): Collection
    {
        $story = collect($paragraphs)
            ->map(fn (string $paragraph, int $index) => sprintf('%d: %s', ++$index, $paragraph))
            ->implode(PHP_EOL);

        return collect($paragraphs)->map(function (string $paragraph) use ($story, $data) {

            $schema = [
                'type' => 'object',
                'required' => [ 'illustration' ],
                'properties' => [
                    'illustration' => [
                        'type' => 'string',
                    ],
                ],
            ];

            $exclusion = match ($data instanceof GenerationDataAdvanced) {
                true => "Ensure the prompt does not make any mention or include these elements: '$data->negative'",
                false => '',
            };

            $prompt = <<<PROMPT
            Generate a creative image prompt for a generative AI tool to create an illustration for the following paragraph in the children's book. You will receive the full story context for reference, but respond with one image prompt at a time, focusing on the provided paragraph.

            ----- start_of_story
            $story
            ----- end_of_story

            For the provided paragraph, follow these instructions:
            1. **Summarize the main action** occurring in the paragraph.
            2. **Describe the setting, mood, and characters** with enough detail so the AI can visualize the scene, ensuring it makes sense even when the paragraph is isolated.
            3. **Use broad character descriptions** rather than names. For example, if the paragraph mentions a character like "Sammy," refer to them as "a turtle," or "Willy" as "a boy."
            4. **Ensure consistency** with the overall visual style and tone of the full story.

            ----- start_of_paragraph
            $paragraph
            ----- end_of_paragraph

            $exclusion

            For the paragraph provided, do the following:
            1. Identify the key scene and describe it in detail, including any relevant emotions or atmosphere.
            2. Use context from the full story to maintain accuracy and consistency in tone and style.

            Respond in JSON format for the paragraph provided.
            PROMPT;

            return [
                $prompt,
                $schema,
            ];

        });
    }
}
