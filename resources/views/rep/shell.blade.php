<!DOCTYPE html>
<html lang="en">
<head>
    @include('rep.partials.head', ['title' => 'DukaFlow — Today'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <script>
        window.DUKAFLOW_REP = { id: {{ Illuminate\Support\Js::from(auth()->id() !== null ? app(\App\Support\Contracts\RepDirectory::class)->repIdForUser(auth()->id()) : null) }}, name: {{ Illuminate\Support\Js::from($repName) }} };
    </script>

    <div x-data="repApp()" x-init="init()" class="flex flex-col min-h-screen">
        {{-- Status bar --}}
        <header class="sticky top-0 z-10 bg-slate-900 border-b border-slate-800 px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-slate-500'"></span>
                    <span class="text-sm text-slate-300" x-text="online ? 'Online' : 'Offline'"></span>
                </div>
                <div class="flex items-center gap-3">
                    <template x-if="pendingCount > 0">
                        <span class="text-xs rounded-full bg-amber-500/20 text-amber-300 px-2 py-1" x-text="pendingCount + ' waiting'"></span>
                    </template>
                    <template x-if="conflictCount > 0">
                        <span class="text-xs rounded-full bg-red-500/20 text-red-300 px-2 py-1" x-text="conflictCount + ' flagged'"></span>
                    </template>
                    <button type="button" @click="trySync()" :disabled="syncing || !online"
                        class="text-xs rounded-lg bg-slate-800 px-3 py-1.5 disabled:opacity-40">
                        <span x-show="!syncing">Sync now</span>
                        <span x-show="syncing">Syncing…</span>
                    </button>
                </div>
            </div>
            <p class="mt-1 text-xs text-slate-500" x-show="lastSyncedAt" x-text="'Last synced ' + new Date(lastSyncedAt).toLocaleTimeString()"></p>
            <p class="mt-1 text-xs text-amber-400" x-show="lastMessage" x-text="lastMessage"></p>
        </header>

        <main class="flex-1 px-4 py-4">
            {{-- Today's round --}}
            <section x-show="view === 'route'">
                <div class="flex items-center justify-between mb-3">
                    <h1 class="text-lg font-semibold">Today's round</h1>
                    <span class="text-sm text-slate-400" x-text="customers.length + ' stops'"></span>
                </div>

                <template x-if="customers.length === 0">
                    <p class="text-sm text-slate-500 py-8 text-center">Nothing scheduled today, or the round hasn't synced yet.</p>
                </template>

                <ul class="space-y-2">
                    <template x-for="customer in customers" :key="customer.id">
                        <li>
                            <button type="button" @click="openCustomer(customer)"
                                class="w-full text-left rounded-xl bg-slate-900 border border-slate-800 px-4 py-3 flex items-center justify-between active:bg-slate-800">
                                <div>
                                    <p class="font-medium" x-text="customer.name"></p>
                                    <p class="text-xs text-slate-500" x-text="customer.owner_name ?? customer.address ?? ''"></p>
                                </div>
                                <span x-show="visitedToday.has(customer.id)" class="text-emerald-400 text-lg">✓</span>
                            </button>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- One customer --}}
            <section x-show="view === 'visit'" x-cloak>
                <button type="button" @click="backToRoute()" class="text-sm text-slate-400 mb-4">← Back to round</button>

                <h1 class="text-lg font-semibold" x-text="activeCustomer?.name"></h1>
                <p class="text-sm text-slate-500 mb-6" x-text="activeCustomer?.address"></p>

                <div class="grid grid-cols-1 gap-3">
                    <button type="button" @click="view = 'cart'"
                        class="rounded-xl bg-amber-500 text-slate-900 font-semibold py-4">
                        Take an order
                    </button>

                    <div class="rounded-xl bg-slate-900 border border-slate-800 p-4">
                        <label class="block text-sm text-slate-300 mb-2">No sale — why?</label>
                        <textarea x-model="noSaleReason" rows="2" placeholder="Shop closed, owner away, no stock needed…"
                            class="w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-2 text-sm"></textarea>
                        <button type="button" @click="recordNoSale()" :disabled="!noSaleReason.trim()"
                            class="mt-3 w-full rounded-lg bg-slate-700 py-2.5 text-sm disabled:opacity-40">
                            Record no sale
                        </button>
                    </div>
                </div>
            </section>

            {{-- Order capture --}}
            <section x-show="view === 'cart'" x-cloak>
                <button type="button" @click="view = 'visit'" class="text-sm text-slate-400 mb-4">← Back</button>
                <h1 class="text-lg font-semibold mb-4" x-text="'Order for ' + (activeCustomer?.name ?? '')"></h1>

                <ul class="space-y-2 mb-6">
                    <template x-for="product in cartProducts" :key="product.product_id">
                        <li class="rounded-xl bg-slate-900 border border-slate-800 px-4 py-3 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium" x-text="product.name"></p>
                                <p class="text-xs text-slate-500" x-text="product.sku + ' · ' + formatMoney(product.unit_price_minor) + ' ETB / ' + product.unit"></p>
                            </div>
                            <button type="button" @click="addToCart(product)"
                                class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm">Add</button>
                        </li>
                    </template>
                </ul>

                <template x-if="cart.length > 0">
                    <div class="rounded-xl bg-slate-900 border border-slate-800 p-4">
                        <h2 class="text-sm font-semibold text-slate-300 mb-2">This order</h2>
                        <ul class="space-y-2">
                            <template x-for="line in cart" :key="line.product_id">
                                <li class="flex items-center justify-between text-sm">
                                    <span x-text="line.name"></span>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="changeQuantity(line.product_id, -1)" class="w-7 h-7 rounded bg-slate-800">−</button>
                                        <span x-text="line.quantity" class="w-6 text-center"></span>
                                        <button type="button" @click="changeQuantity(line.product_id, 1)" class="w-7 h-7 rounded bg-slate-800">+</button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                        <div class="mt-3 pt-3 border-t border-slate-800 flex items-center justify-between font-medium">
                            <span>Total</span>
                            <span x-text="formatMoney(cartTotal()) + ' ETB'"></span>
                        </div>
                        <button type="button" @click="completeOrder()"
                            class="mt-4 w-full rounded-lg bg-amber-500 text-slate-900 font-semibold py-3">
                            Complete order
                        </button>
                    </div>
                </template>
            </section>
        </main>

        <footer class="px-4 py-3 text-center text-xs text-slate-600">
            {{ $repName }} ·
            <form method="POST" action="{{ route('rep.logout') }}" class="inline">
                @csrf
                <button type="submit" class="underline">sign out</button>
            </form>
        </footer>
    </div>

    @vite(['resources/js/rep.js'])
</body>
</html>
