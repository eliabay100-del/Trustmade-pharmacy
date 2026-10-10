<?php
    $isActive = request()->routeIs($route) || request()->routeIs(str($route)->before('.').'.*');
?>
<a href="<?php echo e(route($route)); ?>"
   class="flex items-center gap-3 px-3 py-2 rounded-lg mb-0.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#12A594]
   <?php echo e($isActive
        ? 'bg-[#12A594]/15 text-white border-l-2 border-[#12A594]'
        : 'text-[#B9C2D0] hover:bg-white/5 hover:text-white border-l-2 border-transparent'); ?>">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0 <?php echo e($isActive ? 'text-[#12A594]' : 'text-[#6B7A93]'); ?>"
         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <?php echo e($slot); ?>

    </svg>
    <span class="text-sm <?php echo e($isActive ? 'font-medium' : ''); ?>"><?php echo e($label); ?></span>
</a>
<?php /**PATH /workspace/resources/views/components/nav-link.blade.php ENDPATH**/ ?>