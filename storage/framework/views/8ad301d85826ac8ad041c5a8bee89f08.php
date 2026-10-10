<?php $__env->startSection('content'); ?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#1E2530]">Alerts</h1>
    <p class="text-sm text-[#6B7A93] mt-1">Expiry warnings and customers due for a refill reminder.</p>
</div>

<div class="space-y-6">

    
    <?php if(!empty($supplierReminders)): ?>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-red-50 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <div><h2 class="font-bold text-[#1E2530]">Owed to suppliers — Overdue</h2><p class="text-xs text-[#6B7A93] mt-0.5">Past their due date. Pay these first.</p></div>
            </div>
            <?php if(count($supplierReminders['overdue'])): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-50"><tbody class="divide-y divide-gray-50">
                        <?php $__currentLoopData = $supplierReminders['overdue']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3 text-sm font-medium text-[#1E2530]"><?php echo e($item['supplier_name']); ?></td>
                                <td class="px-5 py-3 text-xs text-[#6B7A93]"><?php echo e($item['supplier_invoice_no'] ?: $item['purchase_ref']); ?></td>
                                <td class="px-5 py-3 text-sm text-right text-red-600 font-semibold">ETB <?php echo e(number_format($item['balance'], 2)); ?></td>
                                <td class="px-5 py-3 text-sm text-right text-red-600">Due <?php echo e($item['due_date']->format('d M Y')); ?></td>
                                <td class="px-5 py-3 text-right"><a href="<?php echo e(route('supplier-payments.create', $item['supplier_id'])); ?>" class="text-xs font-medium text-[#12A594] hover:underline">Pay supplier</a></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody></table>
                </div>
            <?php else: ?>
                <p class="px-5 py-6 text-sm text-[#6B7A93]">Nothing is overdue. Good.</p>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <span class="w-7 h-7 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 font-bold">7</span>
                <div><h2 class="font-bold text-[#1E2530]">Payments due within <?php echo e($supplierReminders['soon_days']); ?> days</h2><p class="text-xs text-[#6B7A93] mt-0.5">Get ready to pay these suppliers.</p></div>
            </div>
            <?php if(count($supplierReminders['due_soon'])): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-50"><tbody class="divide-y divide-gray-50">
                        <?php $__currentLoopData = $supplierReminders['due_soon']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3 text-sm font-medium text-[#1E2530]"><?php echo e($item['supplier_name']); ?></td>
                                <td class="px-5 py-3 text-xs text-[#6B7A93]"><?php echo e($item['supplier_invoice_no'] ?: $item['purchase_ref']); ?></td>
                                <td class="px-5 py-3 text-sm text-right text-amber-600 font-semibold">ETB <?php echo e(number_format($item['balance'], 2)); ?></td>
                                <td class="px-5 py-3 text-sm text-right text-amber-600">Due <?php echo e($item['due_date']->format('d M Y')); ?></td>
                                <td class="px-5 py-3 text-right"><a href="<?php echo e(route('supplier-payments.create', $item['supplier_id'])); ?>" class="text-xs font-medium text-[#12A594] hover:underline">Pay supplier</a></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody></table>
                </div>
            <?php else: ?>
                <p class="px-5 py-6 text-sm text-[#6B7A93]">No supplier payments are due in the next <?php echo e($supplierReminders['soon_days']); ?> days.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-amber-50 flex items-center justify-center text-amber-600 font-bold">!</span>
            <div><h2 class="font-bold text-[#1E2530]">Low Stock — Restock Soon</h2><p class="text-xs text-[#6B7A93] mt-0.5">Items at or below their minimum stock level.</p></div>
        </div>
        <?php if($lowStockDrugs->count() || $lowStockProducts->count()): ?>
            <table class="min-w-full divide-y divide-gray-50"><tbody class="divide-y divide-gray-50">
                <?php $__currentLoopData = $lowStockDrugs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drug): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="hover:bg-gray-50/50"><td class="px-5 py-3 text-sm font-medium text-[#1E2530]"><?php echo e($drug->name); ?></td><td class="px-5 py-3 text-xs text-[#6B7A93]">Drug</td><td class="px-5 py-3 text-sm text-right text-amber-600"><?php echo e(number_format((float) $drug->purchaseBatches->sum('quantity_remaining'), 0)); ?> left <span class="text-xs text-[#6B7A93]">/ minimum <?php echo e(number_format((float) $drug->minimum_stock_level, 0)); ?></span></td><td class="px-5 py-3 text-right"><a href="<?php echo e(route('drugs.index', ['q' => $drug->name])); ?>" class="text-xs font-medium text-[#12A594] hover:underline">View drug</a></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php $__currentLoopData = $lowStockProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="hover:bg-gray-50/50"><td class="px-5 py-3 text-sm font-medium text-[#1E2530]"><?php echo e($product->name); ?></td><td class="px-5 py-3 text-xs text-[#6B7A93]">Product</td><td class="px-5 py-3 text-sm text-right text-amber-600"><?php echo e(number_format((float) $product->purchaseBatches->sum('quantity_remaining'), 0)); ?> left <span class="text-xs text-[#6B7A93]">/ minimum <?php echo e(number_format((float) $product->minimum_stock_level, 0)); ?></span></td><td class="px-5 py-3 text-right"><a href="<?php echo e(route('products.index', ['q' => $product->name])); ?>" class="text-xs font-medium text-[#12A594] hover:underline">View product</a></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody></table>
        <?php else: ?>
            <p class="px-5 py-6 text-sm text-[#6B7A93]">No items are currently below their minimum stock level.</p>
        <?php endif; ?>
    </div>

    
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-red-50 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <h2 class="font-bold text-[#1E2530]">Expired Stock — Remove from Shelf</h2>
        </div>
        <?php if($expired->count() > 0): ?>
            <table class="min-w-full divide-y divide-gray-50">
                <tbody class="divide-y divide-gray-50">
                    <?php $__currentLoopData = $expired; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $batch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-sm font-medium text-[#1E2530]"><?php echo e($batch->drug->name ?? '—'); ?></td>
                            <td class="px-5 py-3 text-sm text-[#6B7A93]">Batch <?php echo e($batch->batch_number); ?></td>
                            <td class="px-5 py-3 text-sm text-right text-[#6B7A93]"><?php echo e($batch->quantity_remaining); ?> units</td>
                            <td class="px-5 py-3 text-sm text-right">
                                <a href="<?php echo e(route('adjustments.create', ['batch_id' => $batch->id])); ?>" class="text-[#12A594] hover:underline text-xs font-medium">Write off</a>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="px-5 py-6 text-sm text-[#6B7A93]">No expired stock currently on hand.</p>
        <?php endif; ?>
    </div>

    
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-amber-50 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <h2 class="font-bold text-[#1E2530]">Expiring Within 30 Days</h2>
        </div>
        <?php if($expiringSoon->count() > 0): ?>
            <table class="min-w-full divide-y divide-gray-50">
                <tbody class="divide-y divide-gray-50">
                    <?php $__currentLoopData = $expiringSoon; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $batch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-sm font-medium text-[#1E2530]"><?php echo e($batch->drug->name ?? '—'); ?></td>
                            <td class="px-5 py-3 text-sm text-[#6B7A93]">Batch <?php echo e($batch->batch_number); ?></td>
                            <td class="px-5 py-3 text-sm text-right text-[#6B7A93]"><?php echo e($batch->quantity_remaining); ?> units</td>
                            <td class="px-5 py-3 text-sm text-right">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-600">
                                    <?php echo e(\Carbon\Carbon::parse($batch->expiry_date)->format('M d, Y')); ?>

                                </span>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="px-5 py-6 text-sm text-[#6B7A93]">Nothing expiring in the next 30 days.</p>
        <?php endif; ?>
    </div>

    
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="w-7 h-7 rounded-full bg-[#12A594]/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#12A594]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-4a4 4 0 100-8 4 4 0 000 8zm6 4a4 4 0 00-8 0" />
                </svg>
            </span>
            <h2 class="font-bold text-[#1E2530]">Customers Due for Refill</h2>
        </div>
        <?php if($dueForRefill->count() > 0): ?>
            <table class="min-w-full divide-y divide-gray-50">
                <tbody class="divide-y divide-gray-50">
                    <?php $__currentLoopData = $dueForRefill; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-sm font-medium text-[#1E2530]"><?php echo e($customer->name); ?></td>
                            <td class="px-5 py-3 text-sm text-[#6B7A93]"><?php echo e($customer->condition_note ?? '—'); ?></td>
                            <td class="px-5 py-3 text-sm text-right text-[#6B7A93]"><?php echo e($customer->phone ?? 'no phone on file'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="px-5 py-6 text-sm text-[#6B7A93]">No recurring customers due for a reminder right now.</p>
        <?php endif; ?>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /workspace/resources/views/alerts/index.blade.php ENDPATH**/ ?>