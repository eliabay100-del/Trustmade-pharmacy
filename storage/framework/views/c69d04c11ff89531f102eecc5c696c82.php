<?php $__env->startSection('content'); ?>
<div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-[#10253b] px-6 py-7 text-white shadow-sm sm:px-8">
        <div class="absolute -right-12 -top-20 h-56 w-56 rounded-full border border-[#58c6b6]/20"></div>
        <div class="absolute right-16 -bottom-24 h-48 w-48 rounded-full border border-white/10"></div>
        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-[.18em] text-[#72d4c5]">Pharmacy operations</p>
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Good morning, <?php echo e(explode(' ', trim(auth()->user()->name))[0]); ?>.</h1>
                <p class="mt-2 max-w-xl text-sm text-white/65">Here is the live picture of your pharmacy today.</p>
            </div>
            <div class="shrink-0 rounded-xl border border-white/10 bg-white/10 px-4 py-3 text-left backdrop-blur-sm sm:text-right">
                <p class="text-xs text-white/55"><?php echo e(now()->format('l, d M Y')); ?></p>
                <p class="mt-1 text-sm font-semibold"><?php echo e(auth()->user()->branch->name ?? 'All branches'); ?></p>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5" aria-label="Pharmacy summary">
        <a href="<?php echo e(route('drugs.index')); ?>" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-[#0f9f8f] hover:shadow-md">
            <div class="flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-[#6b7a93]">Drugs</span><span class="rounded-lg bg-[#e7f7f4] px-2 py-1 text-[#0f9f8f]">RX</span></div>
            <p class="mt-5 text-3xl font-bold text-[#182230] group-hover:text-[#0f9f8f]"><?php echo e($totalDrugs); ?></p><p class="mt-1 text-xs text-[#6b7a93]">View drug catalogue</p>
        </a>
        <a href="<?php echo e(route('products.index')); ?>" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-[#0f9f8f] hover:shadow-md">
            <div class="flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-[#6b7a93]">Products</span><span class="rounded-lg bg-[#fff2dc] px-2 py-1 text-[#b36b00]">RET</span></div>
            <p class="mt-5 text-3xl font-bold text-[#182230] group-hover:text-[#0f9f8f]"><?php echo e($totalProducts); ?></p><p class="mt-1 text-xs text-[#6b7a93]">View retail catalogue</p>
        </a>
        <div class="rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm"><span class="text-xs font-semibold uppercase tracking-wide text-[#6b7a93]">Today's sales</span><p class="mt-5 text-2xl font-bold text-[#182230]">ETB <?php echo e(number_format($todaySales, 2)); ?></p><p class="mt-1 text-xs text-[#6b7a93]">Collected today</p></div>
        <a href="<?php echo e(route('alerts.index')); ?>" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-amber-400 hover:shadow-md"><span class="text-xs font-semibold uppercase tracking-wide text-[#6b7a93]">Low stock</span><p class="mt-5 text-3xl font-bold <?php echo e($lowStockCount ? 'text-amber-600' : 'text-[#182230]'); ?>"><?php echo e($lowStockCount); ?></p><p class="mt-1 text-xs text-[#6b7a93]">Open stock alerts</p></a>
        <a href="<?php echo e(route('alerts.index')); ?>" class="group rounded-xl border border-[#dfe7ed] bg-white p-5 shadow-sm hover:-translate-y-0.5 hover:border-red-400 hover:shadow-md"><span class="text-xs font-semibold uppercase tracking-wide text-[#6b7a93]">Expiring soon</span><p class="mt-5 text-3xl font-bold <?php echo e($expiringSoonCount ? 'text-red-600' : 'text-[#182230]'); ?>"><?php echo e($expiringSoonCount); ?></p><p class="mt-1 text-xs text-[#6b7a93]">Next 30 days</p></a>
    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-[1.2fr_.8fr]">
        <div class="rounded-xl border border-[#dfe7ed] bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase tracking-wide text-[#6b7a93]">Today's pulse</p><h2 class="mt-1 text-xl font-bold text-[#182230]">Keep the shelves moving</h2></div><a href="<?php echo e(route('reports.sales')); ?>" class="text-sm font-semibold text-[#0f9f8f] hover:underline">Sales report</a></div>
            <div class="mt-7 space-y-5">
                <div><div class="mb-2 flex justify-between text-sm"><span class="text-[#6b7a93]">Sales, last 7 days</span><strong class="text-[#182230]">ETB <?php echo e(number_format($recentSales, 2)); ?></strong></div><div class="h-2 overflow-hidden rounded-full bg-[#e8eef1]"><div class="h-full rounded-full bg-[#0f9f8f]" style="width: <?php echo e(min(100, max(4, (float) $recentSales / max(1, (float) $recentSales + (float) $recentPurchases) * 100))); ?>%"></div></div></div>
                <div><div class="mb-2 flex justify-between text-sm"><span class="text-[#6b7a93]">Purchases, last 7 days</span><strong class="text-[#182230]">ETB <?php echo e(number_format($recentPurchases, 2)); ?></strong></div><div class="h-2 overflow-hidden rounded-full bg-[#e8eef1]"><div class="h-full rounded-full bg-[#e8a43a]" style="width: <?php echo e(min(100, max(4, (float) $recentPurchases / max(1, (float) $recentSales + (float) $recentPurchases) * 100))); ?>%"></div></div></div>
            </div>
            <div class="mt-7 grid grid-cols-2 gap-3 border-t border-[#edf1f3] pt-5"><div><p class="text-xs text-[#6b7a93]">Total units on hand</p><p class="mt-1 text-lg font-bold text-[#182230]"><?php echo e(number_format($totalQuantity)); ?></p></div><div><p class="text-xs text-[#6b7a93]">Product sales</p><p class="mt-1 text-lg font-bold text-[#182230]">ETB <?php echo e(number_format($productSales, 2)); ?></p></div></div>
        </div>
        <div class="rounded-xl border border-[#dfe7ed] bg-white p-6 shadow-sm"><div class="flex items-start justify-between"><div><p class="text-xs font-semibold uppercase tracking-wide text-[#6b7a93]">Shortcuts</p><h2 class="mt-1 text-xl font-bold text-[#182230]">Common tasks</h2></div><span class="text-xl text-[#0f9f8f]">+</span></div><div class="mt-5 grid gap-3 sm:grid-cols-3 lg:grid-cols-1"><a href="<?php echo e(route('dispense.create')); ?>" class="rounded-lg bg-[#e7f7f4] px-4 py-3 text-sm font-semibold text-[#08796f] hover:bg-[#d7f1ed]">Start a sale <span class="float-right">→</span></a><a href="<?php echo e(route('purchases.create')); ?>" class="rounded-lg bg-[#fff2dc] px-4 py-3 text-sm font-semibold text-[#8a5700] hover:bg-[#ffebc8]">Record purchase <span class="float-right">→</span></a><a href="<?php echo e(route('adjustments.create')); ?>" class="rounded-lg bg-[#eef1f5] px-4 py-3 text-sm font-semibold text-[#405166] hover:bg-[#e5eaf0]">Adjust stock <span class="float-right">→</span></a></div></div>
    </section>

    <section class="rounded-xl border border-[#dfe7ed] bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase tracking-wide text-[#6b7a93]">Attention needed</p><h2 class="mt-1 text-xl font-bold text-[#182230]">Recent alerts</h2></div><a href="<?php echo e(route('alerts.index')); ?>" class="text-sm font-semibold text-[#0f9f8f] hover:underline">View all</a></div><?php if($expiringSoonCount || $lowStockCount): ?><div class="mt-5 grid gap-3 sm:grid-cols-2"><?php if($expiringSoonCount): ?><div class="flex items-center gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800"><span class="h-2 w-2 rounded-full bg-red-500"></span><?php echo e($expiringSoonCount); ?> drug<?php echo e($expiringSoonCount === 1 ? '' : 's'); ?> expire within 30 days</div><?php endif; ?> <?php if($lowStockCount): ?><div class="flex items-center gap-3 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800"><span class="h-2 w-2 rounded-full bg-amber-500"></span><?php echo e($lowStockCount); ?> item<?php echo e($lowStockCount === 1 ? '' : 's'); ?> need restocking</div><?php endif; ?></div><?php else: ?><div class="mt-5 rounded-lg bg-[#e7f7f4] px-4 py-3 text-sm text-[#08796f]">Everything is in good shape. No active alerts.</div><?php endif; ?></section>

    
    <?php if(!empty($supplierReminders) && (count($supplierReminders['overdue']) || count($supplierReminders['due_soon']))): ?>
        <section class="rounded-xl border border-[#dfe7ed] bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><div><p class="text-xs font-semibold uppercase tracking-wide text-[#6b7a93]">Owed to suppliers</p><h2 class="mt-1 text-xl font-bold text-[#182230]">Payments coming due</h2></div><a href="<?php echo e(route('supplier-owed.index')); ?>" class="text-sm font-semibold text-[#0f9f8f] hover:underline">See all</a></div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <?php if(count($supplierReminders['overdue'])): ?>
                    <a href="<?php echo e(route('supplier-owed.index')); ?>" class="flex items-center gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800 hover:border-red-300 min-h-[44px]"><span class="h-2 w-2 rounded-full bg-red-500"></span><?php echo e(count($supplierReminders['overdue'])); ?> payment<?php echo e(count($supplierReminders['overdue']) === 1 ? '' : 's'); ?> overdue — ETB <?php echo e(number_format(array_sum(array_column($supplierReminders['overdue'], 'balance')), 2)); ?></a>
                <?php endif; ?>
                <?php if(count($supplierReminders['due_soon'])): ?>
                    <a href="<?php echo e(route('supplier-owed.index')); ?>" class="flex items-center gap-3 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800 hover:border-amber-300 min-h-[44px]"><span class="h-2 w-2 rounded-full bg-amber-500"></span><?php echo e(count($supplierReminders['due_soon'])); ?> due within <?php echo e($supplierReminders['soon_days']); ?> days — ETB <?php echo e(number_format(array_sum(array_column($supplierReminders['due_soon'], 'balance')), 2)); ?></a>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/resources/views/dashboard/index.blade.php ENDPATH**/ ?>