```blade
@extends('layouts.app')

@section('content')

@php
    $packingData = $drugs->mapWithKeys(function ($drug) {
        return [
            $drug->catalog_key => [
                'base' => $drug->baseUnitLabel(),
                'strip' => $drug->unitsPerStrip(),
                'pack' => $drug->unitsPerPack(),
            ],
        ];
    });
@endphp

<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">New Sale</h1>

    <p class="text-sm text-[#6B7A93] mb-6">
        Add drugs or products to the cart, then check out as a single sale.
    </p>

    <form method="POST" action="{{ route('dispense.store') }}" class="space-y-5">
        @csrf

        <!-- ========================================================= -->
        <!-- CART -->
        <!-- ========================================================= -->

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">

            <label class="block text-sm font-medium text-[#1E2530] mb-3">
                Cart
            </label>

            <div class="relative mb-4">
                <input id="catalog-search" type="search" autocomplete="off" placeholder="Search drugs or products by name, strength, or SKU" class="w-full border border-gray-200 rounded-lg px-4 py-3 text-sm focus:border-[#0f9f8f] focus:ring-4 focus:ring-[#0f9f8f]/10">
                <span id="catalog-search-status" class="pointer-events-none absolute right-3 top-3 text-xs text-[#9aa6b6]"></span>
                <div id="catalog-results" class="absolute left-0 right-0 top-full z-20 mt-1 hidden max-h-64 overflow-y-auto rounded-xl border border-[#dfe7ed] bg-white p-1 shadow-lg"></div>
            </div>

            <!-- Cart column headings -->
            <div class="hidden sm:grid grid-cols-12 gap-3 mb-2 px-1">
                <div class="col-span-5 text-xs font-medium text-[#6B7A93]">
                    Item
                </div>

                <div class="col-span-2 text-xs font-medium text-[#6B7A93]">
                    Unit
                </div>

                <div class="col-span-2 text-xs font-medium text-[#6B7A93]">
                    Qty
                </div>

                <div class="col-span-2 text-xs font-medium text-[#6B7A93]">
                    Price
                </div>

                <div class="col-span-1"></div>
            </div>

            <div id="cart-lines" class="space-y-2">

                <!-- First cart line -->
                <div class="grid grid-cols-12 gap-3 items-center cart-line">

                    <!-- Drug -->
                    <div class="col-span-5">
                        <select
                            name="items[0][drug_id]"
                            class="drug-select w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >
                            <option value="">-- Select Item --</option>

                            @foreach($drugs as $drug)
                                <option value="{{ $drug->catalog_key }}">
                                    {{ $drug->name }}
                                    @if($drug->strength ?? false)
                                        ({{ $drug->strength }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Unit -->
                    <div class="col-span-2">
                        <select
                            name="items[0][unit]"
                            class="unit-select w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >
                            <option value="tablet">Base</option>
                            <option value="strip">Strip</option>
                            <option value="pack">Pack</option>
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div class="col-span-2">
                        <input
                            type="number"
                            min="1"
                            name="items[0][quantity]"
                            placeholder="Qty"
                            value="1"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >
                    </div>

                    <!-- Price -->
                    <div class="col-span-2">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="items[0][selling_price]"
                            placeholder="Price"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >
                    </div>

                    <!-- Remove -->
                    <div class="col-span-1 text-center"></div>

                </div>

            </div>

            <p class="text-xs text-[#6B7A93] mt-3">
                Qty and price are for the unit you pick (pack/strip/base).
                Stock is deducted in base units.
            </p>

            <button
                type="button"
                id="add-line-btn"
                class="mt-3 text-[#12A594] text-sm font-semibold hover:underline"
            >
                + Add another item
            </button>

            @if($drugs->isEmpty())
                <p class="text-sm text-red-600 mt-2">
                    No drugs or products in the catalog yet —
                    <a
                        href="{{ route('drugs.create') }}"
                        class="underline"
                    >
                        add one first
                    </a>.
                </p>
            @endif

        </div>


        <!-- ========================================================= -->
        <!-- CUSTOMER -->
        <!-- ========================================================= -->

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">

            <label class="block text-sm font-medium text-[#1E2530] mb-2.5">
                Customer
            </label>

            <div class="flex flex-wrap gap-4 mb-3 text-sm">

                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input
                        type="radio"
                        name="customer_mode"
                        value="anonymous"
                        checked
                        class="customer-mode-radio text-[#12A594]"
                    >
                    Walk-in (no name)
                </label>

                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input
                        type="radio"
                        name="customer_mode"
                        value="new"
                        class="customer-mode-radio text-[#12A594]"
                    >
                    New customer
                </label>

                @if($recurringCustomers->count() > 0)

                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input
                            type="radio"
                            name="customer_mode"
                            value="existing"
                            class="customer-mode-radio text-[#12A594]"
                        >
                        Returning (recurring) customer
                    </label>

                @endif

            </div>


            <!-- New Customer -->

            <div
                id="panel-new"
                class="space-y-3 hidden border border-gray-100 rounded-lg p-4 bg-gray-50/50"
            >

                <div>
                    <label class="block text-xs font-medium text-[#6B7A93] mb-1">
                        Name
                    </label>

                    <input
                        type="text"
                        name="new_customer_name"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
                    >
                </div>


                <div>
                    <label class="block text-xs font-medium text-[#6B7A93] mb-1">
                        Phone
                    </label>

                    <input
                        type="text"
                        name="new_customer_phone"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
                    >
                </div>


                <label class="flex items-center gap-2 text-sm cursor-pointer">

                    <input
                        type="checkbox"
                        name="is_recurring"
                        value="1"
                        id="is-recurring-checkbox"
                    >

                    Mark as recurring customer
                    (get a refill reminder)

                </label>


                <div
                    id="recurring-fields"
                    class="hidden space-y-2 pl-6"
                >

                    <div>

                        <label class="block text-xs font-medium text-[#6B7A93] mb-1">
                            Remind every (days)
                        </label>

                        <input
                            type="number"
                            name="reminder_interval_days"
                            value="30"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
                        >

                    </div>


                    <div>

                        <label class="block text-xs font-medium text-[#6B7A93] mb-1">
                            Note (optional)
                        </label>

                        <input
                            type="text"
                            name="condition_note"
                            placeholder="e.g. Diabetic — monthly refill"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
                        >

                    </div>

                </div>

            </div>


            <!-- Existing Customer -->

            @if($recurringCustomers->count() > 0)

                <div
                    id="panel-existing"
                    class="hidden"
                >

                    <select
                        name="customer_id"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm"
                    >

                        <option value="">
                            -- Select Customer --
                        </option>

                        @foreach($recurringCustomers as $customer)

                            <option value="{{ $customer->id }}">
                                {{ $customer->name }}
                                ({{ $customer->phone ?? 'no phone' }})
                            </option>

                        @endforeach

                    </select>

                </div>

            @endif

        </div>


        <!-- ========================================================= -->
        <!-- PAYMENT -->
        <!-- ========================================================= -->

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">

            <div class="flex justify-between items-baseline mb-2.5">

                <label class="block text-sm font-medium text-[#1E2530]">
                    Payment
                </label>

                <p class="text-sm text-[#6B7A93]">

                    Cart total:

                    <span
                        id="cart-total"
                        class="font-semibold text-[#1E2530]"
                    >
                        0.00
                    </span>

                </p>

            </div>


            <p class="text-xs text-[#6B7A93] mb-3">
                One method for a normal sale, or add another row to split
                (must sum to the cart total).
            </p>


            <div
                id="payment-lines"
                class="space-y-3"
            >

                <div
                    class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end payment-line"
                >

                    <div class="sm:col-span-7">

                        <label class="block text-xs text-[#6B7A93] mb-1">
                            Payment method
                        </label>

                        <select
                            name="payments[0][payment_method]"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >

                            @foreach($paymentMethods as $value => $label)

                                <option
                                    value="{{ $value }}"
                                    @selected($value === 'cash')
                                >
                                    {{ $label }}
                                </option>

                            @endforeach

                        </select>

                    </div>


                    <div class="sm:col-span-4">

                        <label class="block text-xs text-[#6B7A93] mb-1">
                            Amount
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="payments[0][amount]"
                            placeholder="Amount"
                            class="payment-amount w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                            required
                        >

                    </div>


                    <div class="sm:col-span-1"></div>

                </div>

            </div>


            <button
                type="button"
                id="add-payment-btn"
                class="mt-3 text-[#12A594] text-sm font-semibold hover:underline"
            >
                + Split with another method
            </button>

        </div>


        <!-- ========================================================= -->
        <!-- BUTTONS -->
        <!-- ========================================================= -->

        <div class="flex gap-3">

            <button
                type="submit"
                class="bg-[#12A594] hover:bg-[#0e8a7d] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors"
            >
                Complete Sale
            </button>

            <a
                href="{{ route('dispense.index') }}"
                class="text-[#6B7A93] hover:text-[#1E2530] text-sm py-2.5 px-4"
            >
                Cancel
            </a>

        </div>

    </form>

</div>


<script>

(function () {

    // ============================================================
    // CUSTOMER MODE
    // ============================================================

    const radios =
        document.querySelectorAll('.customer-mode-radio');

    const panelNew =
        document.getElementById('panel-new');

    const panelExisting =
        document.getElementById('panel-existing');

    const recurringCheckbox =
        document.getElementById('is-recurring-checkbox');

    const recurringFields =
        document.getElementById('recurring-fields');


    function updatePanels() {

        const checkedRadio =
            document.querySelector(
                '.customer-mode-radio:checked'
            );

        if (!checkedRadio) return;

        const mode =
            checkedRadio.value;


        panelNew.classList.toggle(
            'hidden',
            mode !== 'new'
        );


        if (panelExisting) {

            panelExisting.classList.toggle(
                'hidden',
                mode !== 'existing'
            );

        }

    }


    radios.forEach(function (radio) {

        radio.addEventListener(
            'change',
            updatePanels
        );

    });


    updatePanels();


    if (recurringCheckbox) {

        recurringCheckbox.addEventListener(
            'change',
            function () {

                recurringFields.classList.toggle(
                    'hidden',
                    !this.checked
                );

            }
        );

    }


    // ============================================================
    // CART
    // ============================================================

    const cartLines =
        document.getElementById('cart-lines');

    const addLineBtn =
        document.getElementById('add-line-btn');

    const firstDrugSelect =
        document.querySelector('.drug-select');


    const drugOptionsHtml =
        firstDrugSelect
            ? firstDrugSelect.innerHTML
            : '';

    const catalogSearch = document.getElementById('catalog-search');
    const catalogStatus = document.getElementById('catalog-search-status');

    function addCatalogItem(value) {
        let row = [...document.querySelectorAll('.cart-line')].find(function (cartRow) {
            return !cartRow.querySelector('.drug-select').value;
        });

        if (!row) {
            addLineBtn.click();
            row = cartLines.lastElementChild;
        }

        const select = row.querySelector('.drug-select');
        select.value = value;
        syncLineUnits(row);
        select.dispatchEvent(new Event('change', { bubbles: true }));
        catalogSearch.value = '';
        catalogResults.classList.add('hidden');
        catalogStatus.textContent = 'Added';
        updateCartTotal();
        row.querySelector('input[name*="[quantity]"]')?.focus();
    }

    function searchCatalog() {
        const term = catalogSearch.value.trim().toLowerCase();
        catalogResults.innerHTML = '';
        if (!term) {
            catalogResults.classList.add('hidden');
            catalogStatus.textContent = '';
            return;
        }

        const matches = [...firstDrugSelect.options].filter(option => option.value && option.textContent.toLowerCase().includes(term));
        catalogStatus.textContent = matches.length + ' match' + (matches.length === 1 ? '' : 'es');
        matches.slice(0, 8).forEach(function (option) {
            const result = document.createElement('button');
            result.type = 'button';
            result.className = 'block w-full rounded-lg px-3 py-2 text-left text-sm text-[#182230] hover:bg-[#e7f7f4]';
            result.textContent = option.textContent.trim();
            result.addEventListener('click', () => addCatalogItem(option.value));
            catalogResults.appendChild(result);
        });
        catalogResults.classList.toggle('hidden', matches.length === 0);
    }

    const catalogResults = document.getElementById('catalog-results');
    catalogSearch.addEventListener('input', searchCatalog);
    catalogSearch.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            const firstMatch = catalogResults.querySelector('button');
            if (firstMatch) firstMatch.click();
        }
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('#catalog-search') && !event.target.closest('#catalog-results')) catalogResults.classList.add('hidden');
    });


    // Packing data prepared safely by PHP.
    const packing =
        @json($packingData);


    let lineIndex = 1;


    function unitOptionsHtml(drugId) {

        const p =
            packing[drugId] || {
                base: 'tablet',
                strip: 1,
                pack: 1
            };


        return `<option value="tablet">${p.base} (base)</option>`
            + `<option value="strip">Strip (${p.strip})</option>`
            + `<option value="pack">Pack (${p.pack})</option>`;

    }


    function syncLineUnits(row) {

        const drugSelect =
            row.querySelector(
                'select[name*="[drug_id]"]'
            );


        const unitSelect =
            row.querySelector(
                '.unit-select'
            );


        if (!drugSelect || !unitSelect) {
            return;
        }


        const current =
            unitSelect.value || 'tablet';


        unitSelect.innerHTML =
            unitOptionsHtml(
                drugSelect.value
            );


        if (
            [...unitSelect.options].some(
                function (option) {
                    return option.value === current;
                }
            )
        ) {

            unitSelect.value =
                current;

        }

    }


    // ============================================================
    // ADD DRUG
    // ============================================================

    addLineBtn.addEventListener(
        'click',
        function () {

            const row =
                document.createElement('div');


            // IMPORTANT:
            // Always keep every drug on ONE horizontal row.
            row.className =
                'grid grid-cols-12 gap-3 items-center cart-line';


            row.innerHTML = `

                <div class="col-span-5">

                    <select
                        name="items[${lineIndex}][drug_id]"
                        class="drug-select w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >
                        ${drugOptionsHtml}
                    </select>

                </div>


                <div class="col-span-2">

                    <select
                        name="items[${lineIndex}][unit]"
                        class="unit-select w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >

                        <option value="tablet">
                            Base unit
                        </option>

                        <option value="strip">
                            Strip
                        </option>

                        <option value="pack">
                            Pack
                        </option>

                    </select>

                </div>


                <div class="col-span-2">

                    <input
                        type="number"
                        min="1"
                        name="items[${lineIndex}][quantity]"
                        placeholder="Qty"
                        value="1"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >

                </div>


                <div class="col-span-2">

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="items[${lineIndex}][selling_price]"
                        placeholder="Price"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >

                </div>


                <div class="col-span-1 text-center">

                    <button
                        type="button"
                        class="remove-line-btn text-red-500 hover:text-red-700 text-xl leading-none"
                        title="Remove drug"
                    >
                        &times;
                    </button>

                </div>

            `;


            cartLines.appendChild(row);


            syncLineUnits(row);


            lineIndex++;


            updateCartTotal();

        }
    );


    // ============================================================
    // DRUG CHANGE
    // ============================================================

    cartLines.addEventListener(
        'change',
        function (e) {

            if (
                e.target.matches(
                    'select[name*="[drug_id]"]'
                )
            ) {

                syncLineUnits(
                    e.target.closest('.cart-line')
                );

            }

        }
    );


    // ============================================================
    // REMOVE DRUG
    // ============================================================

    cartLines.addEventListener(
        'click',
        function (e) {

            if (
                e.target.classList.contains(
                    'remove-line-btn'
                )
            ) {

                e.target
                    .closest('.cart-line')
                    .remove();


                updateCartTotal();

            }

        }
    );


    // ============================================================
    // CART INPUT
    // ============================================================

    cartLines.addEventListener(
        'input',
        function (e) {

            if (
                e.target.matches(
                    'input[name*="[quantity]"]'
                )
                ||
                e.target.matches(
                    'input[name*="[selling_price]"]'
                )
            ) {

                updateCartTotal();

            }

        }
    );


    // ============================================================
    // PAYMENTS
    // ============================================================

    const paymentLines =
        document.getElementById(
            'payment-lines'
        );


    const addPaymentBtn =
        document.getElementById(
            'add-payment-btn'
        );


    const paymentSelect =
        paymentLines.querySelector(
            'select'
        );


    const paymentMethodOptionsHtml =
        paymentSelect
            ? paymentSelect.innerHTML
            : '';


    let paymentIndex = 1;


    function cartTotal() {

        let total = 0;


        cartLines
            .querySelectorAll('.cart-line')
            .forEach(function (row) {

                const quantityInput =
                    row.querySelector(
                        'input[name*="[quantity]"]'
                    );


                const priceInput =
                    row.querySelector(
                        'input[name*="[selling_price]"]'
                    );


                const qty =
                    quantityInput
                        ? parseFloat(
                            quantityInput.value
                        ) || 0
                        : 0;


                const price =
                    priceInput
                        ? parseFloat(
                            priceInput.value
                        ) || 0
                        : 0;


                total +=
                    qty * price;

            });


        return Math.round(
            total * 100
        ) / 100;

    }


    function updateCartTotal() {

        const total =
            cartTotal();


        document.getElementById(
            'cart-total'
        ).textContent =
            total.toFixed(2);


        const amounts =
            paymentLines.querySelectorAll(
                '.payment-amount'
            );


        updatePaymentBalance(total);

    }


    function updatePaymentBalance(total = cartTotal()) {

        const lines = [...paymentLines.querySelectorAll('.payment-line')];
        const amounts = lines.map(row => row.querySelector('.payment-amount'));

        if (amounts.length === 1) {
            amounts[0].value = total > 0 ? total.toFixed(2) : '';
            return;
        }

        const cashLine = lines.find(row => row.querySelector('select')?.value === 'cash') || lines[0];
        const cashAmount = cashLine?.querySelector('.payment-amount');
        const otherTotal = lines
            .filter(row => row !== cashLine)
            .reduce((sum, row) => sum + (parseFloat(row.querySelector('.payment-amount')?.value) || 0), 0);

        if (cashAmount) {
            cashAmount.value = Math.max(0, total - otherTotal).toFixed(2);
        }

    }


    // ============================================================
    // ADD PAYMENT METHOD
    // ============================================================

    addPaymentBtn.addEventListener(
        'click',
        function () {

            const row =
                document.createElement('div');


            row.className =
                'grid grid-cols-1 sm:grid-cols-12 gap-3 items-end payment-line';


            row.innerHTML = `

                <div class="sm:col-span-7">

                    <label class="block text-xs text-[#6B7A93] mb-1">
                        Payment method
                    </label>


                    <select
                        name="payments[${paymentIndex}][payment_method]"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >
                        ${paymentMethodOptionsHtml}
                    </select>

                </div>


                <div class="sm:col-span-4">

                    <label class="block text-xs text-[#6B7A93] mb-1">
                        Amount
                    </label>


                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="payments[${paymentIndex}][amount]"
                        placeholder="Amount"
                        class="payment-amount w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm min-h-[42px]"
                        required
                    >

                </div>


                <div class="sm:col-span-1 text-center">

                    <button
                        type="button"
                        class="remove-payment-btn text-red-500 hover:text-red-700 text-xl leading-none"
                        title="Remove payment"
                    >
                        &times;
                    </button>

                </div>

            `;


            paymentLines.appendChild(
                row
            );

            const newPaymentMethod = row.querySelector('select');
            if (newPaymentMethod.options.length > 1) {
                newPaymentMethod.selectedIndex = 1;
            }

            paymentIndex++;
            updatePaymentBalance();

        }
    );


    // ============================================================
    // REMOVE PAYMENT
    // ============================================================

    paymentLines.addEventListener(
        'click',
        function (e) {

            if (
                e.target.classList.contains(
                    'remove-payment-btn'
                )
            ) {

                e.target
                    .closest('.payment-line')
                    .remove();


                updateCartTotal();

            }

        }
    );


    paymentLines.addEventListener(
        'input',
        function (e) {
            if (e.target.matches('.payment-amount') && e.target.closest('.payment-line')?.querySelector('select')?.value !== 'cash') {
                updatePaymentBalance();
            }
        }
    );


    paymentLines.addEventListener(
        'change',
        function (e) {
            if (e.target.matches('select')) {
                updatePaymentBalance();
            }
        }
    );


    // ============================================================
    // INITIAL SETUP
    // ============================================================

    updateCartTotal();


    cartLines
        .querySelectorAll('.cart-line')
        .forEach(
            syncLineUnits
        );

})();

</script>

@endsection
```
