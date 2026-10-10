<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Expenses</h1>
    <a href="<?php echo e(route('expenses.create')); ?>" class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-bold py-2 px-4 rounded-lg text-sm min-h-[44px] inline-flex items-center">+ Add Expense</a>
</div>


<form method="GET" action="<?php echo e(route('expenses.index')); ?>" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-6 grid grid-cols-1 sm:grid-cols-4 gap-3">
    <div>
        <label class="block text-xs font-semibold text-[#6B7A93] mb-1">From date</label>
        <input type="date" name="date_from" value="<?php echo e(request('date_from')); ?>" class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
    </div>
    <div>
        <label class="block text-xs font-semibold text-[#6B7A93] mb-1">To date</label>
        <input type="date" name="date_to" value="<?php echo e(request('date_to')); ?>" class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
    </div>
    <div>
        <label class="block text-xs font-semibold text-[#6B7A93] mb-1">Category</label>
        <select name="category_id" class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
            <option value="">All categories</option>
            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($cat->id); ?>" <?php if(request('category_id') == $cat->id): echo 'selected'; endif; ?>><?php echo e($cat->name); ?><?php if (! ($cat->active)): ?> (inactive) <?php endif; ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="flex items-end gap-2">
        <button type="submit" class="bg-[#101B2D] hover:bg-[#1a2b47] text-white font-semibold py-2 px-4 rounded-lg text-sm min-h-[44px]">Filter</button>
        <a href="<?php echo e(route('expenses.index')); ?>" class="text-[#6B7A93] hover:text-[#1E2530] text-sm py-2 px-2 min-h-[44px] inline-flex items-center">Clear</a>
    </div>
</form>

<div class="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden min-w-[760px]">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50/50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Date</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Category</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Amount</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">How Paid</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Note</th>
                <?php if(auth()->user()->branch_id === null): ?>
                <th class="px-5 py-3 text-left text-xs font-semibold text-[#6B7A93] uppercase tracking-wide">Branch</th>
                <?php endif; ?>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <?php $__empty_1 = true; $__currentLoopData = $expenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $expense): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="hover:bg-gray-50/50">
                    <td class="px-5 py-3.5 text-[#1E2530] whitespace-nowrap"><?php echo e($expense->expense_date->format('d M Y')); ?></td>
                    <td class="px-5 py-3.5 font-medium text-[#1E2530]"><?php echo e($expense->category->name); ?></td>
                    <td class="px-5 py-3.5 text-right font-semibold text-[#1E2530] whitespace-nowrap"><?php echo e(number_format($expense->amount, 2)); ?></td>
                    <td class="px-5 py-3.5 text-[#6B7A93]"><?php echo e($expense->payment_label()); ?><?php if($expense->payment_reference): ?> <span class="text-xs">(<?php echo e($expense->payment_reference); ?>)</span><?php endif; ?></td>
                    <td class="px-5 py-3.5 text-[#6B7A93] max-w-[220px] truncate" title="<?php echo e($expense->note); ?>"><?php echo e($expense->note ?? '—'); ?><?php if($expense->repeat_monthly): ?><span class="ml-1 inline-flex items-center rounded-full bg-sky-50 text-sky-700 text-xs px-2 py-0.5 font-medium">Monthly</span><?php endif; ?></td>
                    <?php if(auth()->user()->branch_id === null): ?>
                    <td class="px-5 py-3.5 text-[#6B7A93]"><?php echo e($expense->branch->name ?? '—'); ?></td>
                    <?php endif; ?>
                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                        <a href="<?php echo e(route('expenses.edit', $expense->id)); ?>" class="text-[#12A594] hover:underline mr-3">Edit</a>
                        <form action="<?php echo e(route('expenses.destroy', $expense->id)); ?>" method="POST" class="inline"
                              onsubmit="return confirm('Remove this <?php echo e($expense->category->name); ?> expense of <?php echo e(number_format($expense->amount, 2)); ?>? It will be cancelled out in the books.');">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="text-red-500 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="8" class="px-5 py-8 text-center text-[#6B7A93]">No expenses found for this filter.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<div class="mt-4"><?php echo e($expenses->links()); ?></div>


<h2 class="text-lg font-bold text-[#1E2530] mt-8 mb-3">Total per category (<?php echo e(now()->format('F Y')); ?>)</h2>
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden max-w-md">
    <table class="min-w-full divide-y divide-gray-100">
        <tbody class="divide-y divide-gray-50">
            <?php $grand = 0; ?>
            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(($monthlyTotals[$cat->id] ?? 0) > 0): ?>
                    <?php $grand += $monthlyTotals[$cat->id]; ?>
                    <tr>
                        <td class="px-5 py-3 text-[#1E2530]"><?php echo e($cat->name); ?></td>
                        <td class="px-5 py-3 text-right font-semibold text-[#1E2530]"><?php echo e(number_format($monthlyTotals[$cat->id], 2)); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <tr class="bg-gray-50/70">
                <td class="px-5 py-3 font-bold text-[#1E2530]">Total expenses</td>
                <td class="px-5 py-3 text-right font-bold text-[#1E2530]"><?php echo e(number_format($grand, 2)); ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/resources/views/expenses/index.blade.php ENDPATH**/ ?>