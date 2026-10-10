<?php $__env->startSection('content'); ?>
<h1 class="text-2xl font-bold text-[#1E2530] mb-6">Add Expense</h1>

<form method="POST" action="<?php echo e(route('expenses.store')); ?>" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 max-w-2xl space-y-5">
    <?php echo csrf_field(); ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Date</label>
            <input type="date" name="expense_date" value="<?php echo e(old('expense_date', now()->toDateString())); ?>" required
                   class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
            <?php $__errorArgs = ['expense_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Category</label>
            <select name="expense_category_id" required class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
                <option value="">Choose a category…</option>
                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($cat->id); ?>" <?php if(old('expense_category_id') == $cat->id): echo 'selected'; endif; ?>><?php echo e($cat->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php $__errorArgs = ['expense_category_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Amount</label>
            <input type="number" step="0.01" min="0.01" name="amount" value="<?php echo e(old('amount')); ?>" required
                   class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
            <?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <?php if(auth()->user()->branch_id === null): ?>
        <div>
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Branch</label>
            <select name="branch_id" class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
                <?php $__currentLoopData = \App\Models\Branch::orderBy('name')->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($branch->id); ?>" <?php if(old('branch_id') == $branch->id): echo 'selected'; endif; ?>><?php echo e($branch->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php $__errorArgs = ['branch_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <?php endif; ?>
    </div>

    
    <div x-data="{ method: '<?php echo e(old('payment_method', 'cash')); ?>' }">
        <label class="block text-sm font-semibold text-[#1E2530] mb-1">How did you pay?</label>
        <div class="flex flex-wrap gap-2">
            <?php $__currentLoopData = $methods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="inline-flex items-center gap-2 border rounded-lg px-3 py-2 min-h-[44px] cursor-pointer <?php echo e(in_array(old('payment_method','cash'), [$value]) ? 'border-[#12A594] bg-[#12A594]/10' : 'border-gray-200'); ?>">
                    <input type="radio" name="payment_method" value="<?php echo e($value); ?>" x-model="method" class="accent-[#12A594]" <?php if(old('payment_method', 'cash') === $value): echo 'checked'; endif; ?>>
                    <span class="text-sm"><?php echo e($label); ?></span>
                </label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php $__errorArgs = ['payment_method'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <div x-show="method !== 'cash' && method !== 'check'" x-cloak class="mt-4">
            <label class="block text-sm font-semibold text-[#1E2530] mb-1">Transaction number / reference</label>
            <input type="text" name="payment_reference" value="<?php echo e(old('payment_reference')); ?>"
                   class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
            <?php $__errorArgs = ['payment_reference'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div x-show="method === 'check'" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Check number</label>
                <input type="text" name="check_number" value="<?php echo e(old('check_number')); ?>"
                       class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
                <?php $__errorArgs = ['check_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Check date</label>
                <input type="date" name="check_date" value="<?php echo e(old('check_date')); ?>"
                       class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
                <?php $__errorArgs = ['check_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div>
                <label class="block text-sm font-semibold text-[#1E2530] mb-1">Bank name</label>
                <input type="text" name="check_bank" value="<?php echo e(old('check_bank')); ?>"
                       class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594] min-h-[44px]">
                <?php $__errorArgs = ['check_bank'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>
    </div>

    <div>
        <label class="block text-sm font-semibold text-[#1E2530] mb-1">Note (optional)</label>
        <textarea name="note" rows="2" class="w-full rounded-lg border-gray-200 focus:border-[#12A594] focus:ring-[#12A594]" placeholder="e.g. March rent, receipt #221"><?php echo e(old('note')); ?></textarea>
        <?php $__errorArgs = ['note'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <label class="flex items-center gap-3 cursor-pointer min-h-[44px]">
        <input type="checkbox" name="repeat_monthly" value="1" class="rounded accent-[#12A594] w-5 h-5" <?php if(old('repeat_monthly')): echo 'checked'; endif; ?>>
        <span class="text-sm text-[#1E2530]">This happens every month (I'll get a reminder when the next one is due)</span>
    </label>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-bold py-2 px-6 rounded-lg min-h-[44px]">Save Expense</button>
        <a href="<?php echo e(route('expenses.index')); ?>" class="text-[#6B7A93] hover:text-[#1E2530] py-2 px-4 min-h-[44px] inline-flex items-center">Cancel</a>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/resources/views/expenses/create.blade.php ENDPATH**/ ?>