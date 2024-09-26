<template>
    <div>
        <div class="bg-white sm:rounded-lg shadow-sm divide-y divide-gray-100">
            <div class="flex items-center px-6 py-4" v-for="invoice in invoices.data" :key="invoice.id">
                <div class="text-sm w-full">
                    {{ invoice.billed_at }}
                </div>

                <div class="text-sm w-full">
                    <div class="px-2">
                        {{ invoice.total }}
                    </div>
                </div>

                <div class="text-sm w-full">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ __('Paid') }}
                    </span>
                </div>

                <div class="text-right w-full">
                    <a class="underline text-sm text-gray-500" :href="invoice.invoice_url" target="_blank">
                       {{ __('Download Invoice') }}
                    </a>
                </div>
            </div>
        </div>

        <paginator class="mt-4" :preserve-scroll="true" :links="invoices.links" />
    </div>
</template>

<script>
    import Paginator from './../Components/Paginator';

    export default {
        components: {
            Paginator,
        },

        props: ['invoices'],
    }
</script>
