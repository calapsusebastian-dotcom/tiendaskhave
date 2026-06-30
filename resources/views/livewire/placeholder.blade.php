<div class="max-w-5xl mx-auto space-y-4 animate-pulse">
    <div class="flex items-center justify-between">
        <div class="h-5 bg-zinc-200 dark:bg-zinc-700 rounded-lg w-48"></div>
        <div class="h-8 bg-zinc-200 dark:bg-zinc-700 rounded-lg w-28"></div>
    </div>
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 h-12"></div>
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
        <div class="h-10 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-100 dark:border-zinc-700"></div>
        @foreach(range(1, 6) as $i)
            <div class="h-12 border-b border-zinc-50 dark:border-zinc-800/60 flex items-center px-4 gap-4">
                <div class="h-3 bg-zinc-100 dark:bg-zinc-700 rounded w-24"></div>
                <div class="h-3 bg-zinc-100 dark:bg-zinc-700 rounded w-32"></div>
                <div class="h-3 bg-zinc-100 dark:bg-zinc-700 rounded w-20 ml-auto"></div>
            </div>
        @endforeach
    </div>
</div>
