<template>

    <Toaster/>

    <Drawer
        v-if="activeBook.content"
        @update:open="activeBook.visible = $event"
        should-scale-background
        :modal="true"
        :open="activeBook.visible"
        direction="bottom"
        :dismissible="false">

        <DrawerContent>
            <Book :book="activeBook.content" @close="activeBook.visible = false"/>
        </DrawerContent>

    </Drawer>

    <div class="flex max-w-4xl justify-center items-center mt-9 mx-auto space-x-8 pb-10 flex-col-reverse lg:flex-row">

        <div class="relative w-auto lg:w-full px-8 sm:px-0 mt-4 lg:mt-0 justify-items-center">

            <Input
                type="text"
                @focus="$event.target.select()"
                class="text-[#230202] text-opacity-70 bg-[#F18533] pl-12 w-auto sm:w-full transition-all placeholder:opacity-25 placeholder:text-[#230202] rounded-full text-md border-none focus-visible:ring-[#230202]"
                readonly
                default-value="zPf8M7V2YznAvLzsnauyBb3gefYpYbrMotzifXJpump"/>

            <div class="absolute h-full items-center flex text-center justify-center left-12 sm:left-4 top-0">
                CA:
            </div>

        </div>

        <div class="flex justify-center items-center space-x-4 text-[#230202]">

            <Manifesto>
                <Manifest
                    class="cursor-pointer size-10 transition-all rounded-full border-4 border-transparent hover:scale-[1.08] hover:border-[white] hover:bg-white hover:shadow-2xl"/>
            </Manifesto>

            <a href="https://dexscreener.com/solana/4sqm5vwisz3a4g2zagvv1ltg3csqmxxbb2qfuucu9jtv" target="_blank">
                <DexScreener
                    class="cursor-pointer size-10 transition-all rounded-full border-4 border-transparent hover:scale-[1.08] hover:border-[white] hover:bg-white hover:shadow-2xl"/>
            </a>

            <a href="https://x.com/DearBookToken" target="_blank">
                <Twitter
                    class="cursor-pointer size-10 transition-all rounded-full border-4 border-transparent hover:scale-[1.08] hover:border-[white] hover:bg-white hover:shadow-2xl"/>
            </a>

            <a href="https://github.com/milewski/dearbook" target="_blank">
                <Github
                    class="cursor-pointer size-10 transition-all rounded-full border-4 border-transparent hover:scale-[1.08] hover:border-[white] hover:bg-white hover:shadow-2xl"/>
            </a>

            <a href="https://t.me/dearbookcommunity" target="_blank">
                <Telegram
                    class="cursor-pointer size-10 transition-all rounded-full border-4 border-transparent hover:scale-[1.08] hover:border-[white] hover:bg-white hover:shadow-2xl"/>
            </a>

            <a href="https://raydium.io/swap/?inputMint=sol&outputMint=zPf8M7V2YznAvLzsnauyBb3gefYpYbrMotzifXJpump" target="_blank" class="font-sans text-sm h-10 bg-[#230202] px-3 items-center flex rounded-full text-white whitespace-nowrap transition-all will-change-transform  hover:scale-[1.08] hover:bg-gray-900 hover:shadow-2xl">
                   Buy $DEARBOOK
            </a>

        </div>

    </div>

    <div class="flex flex-col justify-center items-center space-y-10 my-10 text-black">

        <img src="./assets/logo.png" class="w-44" alt="">

        <h1 class="text-5xl sm:text-6xl text-center font-serif max-w-[750px] text-[#230202] *:hidden *:sm:block">
            The first AI-Powered Children's Book Creator <br> on Solana
        </h1>

        <CreateStory :view-book="viewBook" :loading="loading"/>

        <!--        <div class="w-full max-w-4xl px-8 sm:px-0 p-2 rounded-full flex h-16">-->

        <!--            <div class="relative w-full flex justify-center items-center">-->

        <!--                <Input-->
        <!--                    type="text"-->
        <!--                    :placeholder="randomSearchTermPlaceholder"-->
        <!--                    class="text-[#230202] text-opacity-70 text-ellipsis pr-14 bg-[#F18533] transition-all shadow-xl placeholder:opacity-25 placeholder:text-[#230202] py-8 pl-8 rounded-full text-2xl border-none h-full w-full focus-visible:ring-[#230202]"-->
        <!--                    :disabled="searching"-->
        <!--                    @keydown.enter="onSearch"-->
        <!--                    v-model="searchTerm"/>-->

        <!--                <LoaderPinwheel-->
        <!--                    v-if="searching"-->
        <!--                    :size="40"-->
        <!--                    class="absolute z-10 right-0 bottom-0 top-0 m-auto opacity-25 mr-3 animate-spin text-[#230202]"/>-->

        <!--                <div v-else-->
        <!--                     class="absolute z-10 text-white right-0 m-auto mr-3 opacity-45 hover:opacity-60 transition-all cursor-pointer">-->

        <!--                    <PackageSearch v-if="!searchTerm" :size="40" class="text-[#230202]" @click="onSearch"/>-->

        <!--                    <CircleX v-else :size="40" class="text-[#230202]" @click="searchTerm = null"/>-->

        <!--                </div>-->

        <!--            </div>-->

        <!--        </div>-->

        <div class="space-y-4 px-8 w-full">

            <div v-if="books.length === 0"
                 class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-10 text-center mt-10">

                <div v-for="_ in 4" class="space-y-2 ">

                    <div class="relative rounded-2xl border-8 border-transparent w-full 2xl:w-[360px]">

                        <AspectRatio :ratio="2/3">
                            <Skeleton class="h-full w-full rounded-2xl"/>
                        </AspectRatio>

                    </div>

                    <Skeleton class="h-6 w-10/12 left-0 right-0 mx-auto rounded-2xl"/>

                </div>

            </div>

            <div v-else
                 class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-10 text-center mt-10">

                <div v-for="(book, index) in books"
                     :id="book.id"
                     @keydown.space.prevent="viewBook(book.id)"
                     @keydown.enter.prevent="viewBook(book.id)"
                     :tabindex="0"
                     class="space-y-2 mx-auto relative cursor-pointer w-full ring-offset-background focus-visible:outline-none focus-visible:ring-2 rounded-2xl focus-visible:ring-offset-2 border-none pb-2 focus-visible:ring-[#230202]"
                     @click="viewBook(book.id)">

                    <div
                        class="relative rounded-2xl transition-all hover:scale-[1.08] transform-gpu border-8 border-transparent hover:border-[white] overflow-hidden hover:z-10 hover:shadow-2xl [&:hover~div]:underline [&:hover~div]:z-50"
                        :class="{
                            'hover:rotate-[-1deg]': index % 4 === 0,
                            'hover:rotate-[-2deg]': index % 4 === 1,
                            'hover:rotate-[3deg]':  index % 4 === 2,
                            'hover:rotate-[4deg]':  index % 4 === 3,
                        }">

                        <AspectRatio
                            :ratio="2/3"
                            class="bg-[#230202] bg-opacity-25 bg-cover overflow-hidden">

                            <div v-if="book.id === loading"
                                 class="before:opacity-50 before: before:absolute before:bg-black before:w-full before:h-full before:left-0 before:bottom-0 animate-pulse"/>

                            <LoaderPinwheel
                                v-if="book.id === loading"
                                :size="50"
                                class="absolute z-10 text-white top-0 bottom-0 m-auto w-full justify-center items-center mr-2 animate-spin"/>

                            <img :src="book.cover" alt="">

                        </AspectRatio>

                    </div>

                    <div class="relative text-3xl sm:text-2xl hover:underline">
                        {{ book.title }}
                    </div>

                </div>


            </div>

            <div class="flex justify-center w-full pt-10" v-if="books.length">

                <div @click="refreshSearch"
                     class="cursor-pointer rounded-full overflow-hidden p-2 transition-all border-4 border-transparent hover:scale-[1.08] hover:border-[white] hover:bg-white hover:shadow-2xl">

                    <DicesIcon class="size-10"/>

                </div>

            </div>

        </div>

    </div>

    <div class="w-full flex justify-center mt-20">

        <Manifesto class="text-center">

            <h1 class="cursor-pointer text-5xl hover:underline sm:text-6xl text-center font-serif max-w-[750px] text-[#230202] ">
                Manifesto
            </h1>

        </Manifesto>

    </div>

    <div class="flex justify-center mt-10 sm:mt-40 select-none">
        <img src="./assets/footer.png" class="sm:w-8/12" alt="">
    </div>

</template>

<script lang="ts" setup>

    import Book from './components/Book.vue'
    import { DicesIcon, LoaderPinwheel } from 'lucide-vue-next'
    import CreateStory from './components/CreateStory.vue'
    import { nextTick, ref, watch } from 'vue'
    import { Input } from '../@/components/ui/input'
    import Toaster from '../@/components/ui/sonner/Sonner.vue'
    import { BookId, BookIndexResource, booksList, fetchBookById } from './api.ts'
    import { AspectRatio } from '../@/components/ui/aspect-ratio'
    import { Skeleton } from '../@/components/ui/skeleton'
    import Github from './icons/Github.vue'
    import Twitter from './icons/Twitter.vue'
    import Telegram from './icons/Telegram.vue'
    import Manifest from './icons/Manifest.vue'
    import { Drawer, DrawerContent } from '../@/components/ui/drawer'
    import Manifesto from './components/Manifesto.vue'
    import DexScreener from './icons/DexScreener.vue'

    const activeBook = ref<{ visible: boolean, content: BookIndexResource | null }>({
        visible: false,
        content: null,
    })

    const loading = ref<BookId>()
    const searching = ref(false)
    const tokens: AbortController[] = []
    const books = ref<Array<BookIndexResource>>([])
    const queryString = new URLSearchParams(window.location.search)

    async function viewBook(bookId: BookId) {

        loading.value = bookId

        while (tokens.length) {
            tokens.pop()!.abort()
        }

        const preloadImage = (source: string): Promise<string> => new Promise((resolve, reject) => {

            const controller = new AbortController

            tokens.push(controller)

            return fetch(source, { signal: controller.signal, cache: 'default' })
                .then(response => response.blob())
                .then(blob => URL.createObjectURL(blob))
                .then(resolve)
                .catch(reject)

        })

        const controller = new AbortController

        tokens.push(controller)

        const book = await fetchBookById(bookId, controller)

        /**
         * Preload all assets
         */
        await Promise.all([
            preloadImage(book.backdrop).then(blob => book.backdrop = blob),
            preloadImage(book.cover).then(blob => book.cover = blob),
            ...book.paragraphs.map(paragraph => preloadImage(paragraph.illustration).then(blob => paragraph.illustration = blob)),
        ])

        loading.value = undefined
        activeBook.value.content = book

        await nextTick(() => {
            activeBook.value.visible = true
        })

    }

    /**
     * If there is a ?book=xx on the url launch it straightaway
     */
    if (queryString.has('book')) {
        viewBook(queryString.get('book') as BookId)
    }

    watch(activeBook.value, function ({ visible, content }) {

        if (typeof history.pushState === 'function') {

            const queryString = new URLSearchParams(window.location.search)

            let currentUrl = `${ window.location.protocol }//${ window.location.host }${ window.location.pathname }`

            if (visible && content) {

                queryString.set('book', content.id)

                const path = currentUrl + `?${ queryString }`

                window.history.pushState({ path }, '', path)

            } else {

                queryString.delete('book')

                if (queryString.size > 0) {
                    currentUrl += `?${ queryString }`
                }

                window.history.pushState({ path: currentUrl }, '', currentUrl)

            }

        }

    })

    /**
     * Load initial books list
     */
    booksList().then(response => {
        if (response) {
            books.value = response
        }
    })

    function refreshSearch() {

        books.value = []

        searching.value = true

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        })

        booksList().then(response => {

            if (response) {
                books.value = response
            }

        }).finally(() => {
            searching.value = false
        })

    }

</script>

<style>

    [vaul-drawer]:has(.book) {
        @apply bg-[#242424] border-0;
    }

    .drawer .swiper-pagination-horizontal {

        --swiper-pagination-bottom: 5px;

        @apply bg-white p-2 rounded-full;

    }

</style>
