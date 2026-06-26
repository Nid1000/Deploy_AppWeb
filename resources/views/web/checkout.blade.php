@extends('layouts.storefront', ['title' => 'Checkout'])

@php
    $izipayPayment = $izipayPayment ?? null;
    $selectedPayment = old('metodo_pago', $izipayPayment ? 'izipay' : 'contra_entrega');
@endphp

@if ($izipayPayment)
    @push('head')
        @if (!empty($izipayPayment['cssUrl']))
            <link rel="stylesheet" href="{{ $izipayPayment['cssUrl'] }}">
        @endif
        <script
            src="{{ $izipayPayment['jsUrl'] }}"
            kr-public-key="{{ $izipayPayment['publicKey'] }}"
            kr-post-url-success="{{ $izipayPayment['successUrl'] }}"
            kr-post-url-refused="{{ $izipayPayment['cancelUrl'] }}"
        ></script>
        <style>
            .izipay-embedded-shell .kr-embedded {
                display: block;
                width: 100%;
            }

            .izipay-embedded-shell .kr-pan,
            .izipay-embedded-shell .kr-expiry,
            .izipay-embedded-shell .kr-security-code,
            .izipay-embedded-shell .kr-installment-number,
            .izipay-embedded-shell .kr-first-installment-delay {
                width: 100%;
                min-height: 46px;
                margin: 10px 0;
                border: 1px solid #d6d3d1;
                border-radius: 0.75rem;
                background: #fff;
                padding: 12px;
            }

            .izipay-embedded-shell .kr-payment-button {
                width: 100%;
                min-height: 48px;
                margin-top: 14px;
                border: 0;
                border-radius: 999px;
                background: #0ea5a4;
                color: #fff;
                font-weight: 700;
                box-shadow: 0 14px 26px rgba(14, 165, 164, .22);
            }

            .izipay-embedded-shell .kr-form-error {
                margin-top: 12px;
                color: #dc2626;
                font-size: 0.875rem;
            }
        </style>
    @endpush
@endif

@section('content')
    <section class="page-hero">
        <div class="max-w-3xl">
            <span class="eyebrow">Checkout</span>
            <h2 class="headline mt-4">Confirma tu pedido y revisa cada detalle antes de finalizar tu compra.</h2>
            <p class="subheadline mt-4">Revisamos tu carrito, generamos el pedido y emitimos el comprobante en el mismo flujo.</p>
        </div>
    </section>

    @if ($cartItems->isEmpty())
        <section class="empty-state mt-8">
            <h3 class="text-2xl font-semibold text-stone-900">Tu carrito esta vacio.</h3>
            <p class="mt-2 text-sm text-stone-600">Agrega algunos productos desde el menu para continuar con tu compra.</p>
            <a href="{{ route('web.products') }}" class="btn btn-primary mt-5">Explorar productos</a>
        </section>
    @else
        <section class="mt-8 grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
            <div class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
                <div class="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <p class="eyebrow">Resumen</p>
                        <h3 class="mt-3 text-3xl font-semibold text-stone-900">Productos seleccionados</h3>
                    </div>
                    <form action="{{ route('web.cart.clear') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Vaciar carrito</button>
                    </form>
                </div>

                <div class="space-y-4">
                    @foreach ($cartItems as $item)
                        <div class="rounded-[1.5rem] border border-amber-100 bg-white p-4 shadow-sm">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <img src="{{ $item->imagen_url }}" alt="{{ $item->nombre }}" class="h-20 w-20 rounded-2xl object-cover">
                                <div class="flex-1">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h4 class="text-lg font-semibold text-stone-900">{{ $item->nombre }}</h4>
                                            <p class="text-sm text-stone-500">{{ $item->categoria_nombre ?: 'Producto artesanal' }}</p>
                                        </div>
                                        <p class="text-lg font-semibold text-[var(--color-secondary)]">S/ {{ number_format($item->subtotal, 2) }}</p>
                                    </div>

                                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <form action="{{ route('web.cart.update', $item->id) }}" method="POST" class="flex items-center gap-3">
                                            @csrf
                                            @method('PATCH')
                                            <label class="text-sm text-stone-600">Cantidad</label>
                                            <input type="number" min="0" max="{{ max(1, $item->stock) }}" name="cantidad" value="{{ $item->cantidad }}" class="input w-24">
                                            <button type="submit" class="btn btn-outline-secondary">Actualizar</button>
                                        </form>
                                        <p class="text-sm text-stone-600">Precio unitario: S/ {{ number_format($item->precio, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 rounded-[1.5rem] border border-amber-200 bg-amber-50 p-5">
                    <div class="flex items-center justify-between text-sm text-stone-600">
                        <span>Total del carrito</span>
                        <strong class="text-2xl text-stone-900">S/ {{ number_format($cartTotal, 2) }}</strong>
                    </div>
                </div>
            </div>

            <div class="rounded-[2rem] border border-amber-200 bg-white/90 p-8 shadow-sm">
                <p class="eyebrow">Entrega y comprobante</p>
                <h3 class="mt-3 text-3xl font-semibold text-stone-900">Datos del pedido</h3>

                <form action="{{ route('web.checkout.submit') }}" method="POST" class="mt-6 space-y-4">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="distrito_entrega" class="label">Distrito</label>
                            <select id="distrito_entrega" name="distrito_entrega" required class="input">
                                <option value="">Selecciona un distrito</option>
                                @foreach ($distritos as $distrito)
                                    <option value="{{ $distrito->nombre }}" @selected(old('distrito_entrega', $user['distrito'] ?? '') === $distrito->nombre)>{{ $distrito->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="numero_casa_entrega" class="label">Numero de casa</label>
                            <input id="numero_casa_entrega" name="numero_casa_entrega" type="text" required value="{{ old('numero_casa_entrega', $user['numero_casa'] ?? '') }}" class="input">
                        </div>
                    </div>

                    <div>
                        <label for="direccion_entrega" class="label">Direccion de entrega</label>
                        <input id="direccion_entrega" name="direccion_entrega" type="text" required value="{{ old('direccion_entrega', $user['direccion'] ?? '') }}" class="input">
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="telefono_contacto" class="label">Telefono</label>
                            <input id="telefono_contacto" name="telefono_contacto" type="tel" required value="{{ old('telefono_contacto', $user['telefono'] ?? '') }}" class="input" placeholder="9XXXXXXXX">
                        </div>
                        <div>
                            <label for="fecha_entrega" class="label">Fecha de entrega</label>
                            <input id="fecha_entrega" name="fecha_entrega" type="date" required min="{{ $minDeliveryDate }}" value="{{ old('fecha_entrega', $minDeliveryDate) }}" class="input">
                        </div>
                    </div>

                    <div>
                        <label for="notas" class="label">Notas</label>
                        <textarea id="notas" name="notas" rows="3" class="input min-h-28" placeholder="Instrucciones adicionales">{{ old('notas') }}</textarea>
                    </div>

                    <div class="grid gap-4 md:grid-cols-[1fr_1fr_1.45fr]" data-document-validation data-document-url="{{ route('web.checkout.validate-document') }}">
                        <div>
                            <label for="comprobante_tipo" class="label">Comprobante</label>
                            <select id="comprobante_tipo" name="comprobante_tipo" class="input">
                                <option value="boleta" @selected(old('comprobante_tipo', 'boleta') === 'boleta')>Boleta</option>
                                <option value="factura" @selected(old('comprobante_tipo') === 'factura')>Factura</option>
                            </select>
                        </div>
                        <div>
                            <label for="tipo_documento" class="label">Documento</label>
                            <select id="tipo_documento" name="tipo_documento" class="input">
                                <option value="DNI" @selected(old('tipo_documento', 'DNI') === 'DNI')>DNI</option>
                                <option value="RUC" @selected(old('tipo_documento') === 'RUC')>RUC</option>
                            </select>
                        </div>
                        <div>
                            <label for="numero_documento" class="label">Numero</label>
                            <div class="flex gap-2">
                                <input id="numero_documento" name="numero_documento" type="text" required value="{{ old('numero_documento') }}" class="input min-w-[9ch] flex-1" inputmode="numeric" autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary shrink-0" data-document-lookup>Validar</button>
                            </div>
                        </div>
                        <p class="md:col-span-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-stone-600" data-document-message>
                            <span data-document-message-text>Ingresa un DNI de 8 digitos o un RUC valido de 11 digitos.</span>
                            <span class="hidden font-semibold text-emerald-700" data-document-inline-name></span>
                        </p>
                        <div class="md:col-span-3 hidden rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" data-document-details></div>
                    </div>

                    <div class="rounded-[1.5rem] border border-amber-100 bg-amber-50/60 p-5" data-payment-box>
                        <p class="eyebrow">Pago</p>
                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <label class="rounded-2xl border border-stone-200 bg-white p-4 cursor-pointer">
                                <input type="radio" name="metodo_pago" value="contra_entrega" class="mr-2" @checked($selectedPayment === 'contra_entrega') @disabled($izipayPayment)>
                                <span class="font-semibold text-stone-900">Efectivo</span>
                                <span class="mt-1 block text-xs text-stone-500">Paga al recibir.</span>
                            </label>
                            <label class="rounded-2xl border border-stone-200 bg-white p-4 cursor-pointer">
                                <input type="radio" name="metodo_pago" value="izipay" class="mr-2" @checked($selectedPayment === 'izipay') @disabled($izipayPayment)>
                                <span class="font-semibold text-stone-900">Tarjeta</span>
                                <span class="mt-1 block text-xs text-stone-500">Pago seguro con tarjeta.</span>
                            </label>
                            <label class="rounded-2xl border border-stone-200 bg-white p-4 cursor-pointer">
                                <input type="radio" name="metodo_pago" value="yape" class="mr-2" @checked($selectedPayment === 'yape') @disabled($izipayPayment)>
                                <span class="font-semibold text-stone-900">Yape</span>
                                <span class="mt-1 block text-xs text-stone-500">Escanea el QR.</span>
                            </label>
                        </div>

                        <div class="mt-4 hidden rounded-2xl border border-stone-200 bg-white p-4" data-payment-panel="izipay">
                            <p class="font-semibold text-stone-900">Pago seguro con tarjeta</p>
                            @if ($izipayPayment)
                                <p class="mt-1 text-sm text-stone-600">Pedido #{{ $izipayPayment['pedidoId'] }} creado. Completa el pago seguro con tarjeta.</p>
                                <div class="izipay-embedded-shell mt-4 rounded-2xl border border-teal-100 bg-teal-50/40 p-4">
                                    <div class="mb-3 rounded-xl border border-teal-300 bg-white px-3 py-3 shadow-sm">
                                        <span class="block text-sm font-semibold text-teal-700">Tarjeta</span>
                                        <span class="text-xs text-stone-500">Pago seguro online</span>
                                    </div>
                                    <div class="kr-embedded" kr-form-token="{{ $izipayPayment['formToken'] }}">
                                        <div class="kr-pan"></div>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div class="kr-expiry"></div>
                                            <div class="kr-security-code"></div>
                                        </div>
                                        <div class="kr-installment-number"></div>
                                        <div class="kr-first-installment-delay"></div>
                                        <button type="button" class="kr-payment-button"></button>
                                        <div class="kr-form-error"></div>
                                    </div>
                                    <p class="mt-3 text-center text-xs text-stone-500">Recuerda activar tus compras por internet.</p>
                                </div>
                            @else
                                <p class="mt-1 text-sm text-stone-600">Al confirmar el pedido mostraremos aqui el formulario seguro para pagar con tarjeta.</p>
                            @endif
                        </div>

                        <div class="mt-4 hidden rounded-2xl border border-stone-200 bg-white p-4" data-payment-panel="yape">
                            <div class="grid gap-4 md:grid-cols-[150px_1fr] md:items-center">
                                <img src="{{ $yapeQrUrl }}" alt="QR de Yape" class="h-36 w-36 rounded-2xl border border-stone-200 object-cover">
                                <div>
                                    <p class="font-semibold text-stone-900">Yapea al numero {{ $yapePhone }}</p>
                                    <p class="mt-1 text-sm text-stone-600">Luego escribe el numero de operacion para confirmar tu pedido.</p>
                                    <label for="yape_operacion" class="label mt-4">Numero de operacion</label>
                                    <input id="yape_operacion" name="yape_operacion" value="{{ old('yape_operacion') }}" class="input" inputmode="numeric">
                                </div>
                            </div>
                        </div>

                        <label class="mt-4 flex items-start gap-3 text-sm text-stone-700">
                            <input type="checkbox" name="acepta_pago" value="1" required class="mt-1" @checked(old('acepta_pago') || $izipayPayment) @disabled($izipayPayment)>
                            <span>Acepto que Delicias registre este metodo de pago y confirme el pedido segun disponibilidad.</span>
                        </label>
                    </div>

                    @if ($izipayPayment)
                        <a href="{{ route('web.orders.show', $izipayPayment['pedidoId']) }}" class="btn btn-outline-secondary w-full justify-center">Ver pedido</a>
                    @else
                        <button type="submit" class="btn btn-primary w-full justify-center">Confirmar pedido</button>
                    @endif
                </form>
            </div>
        </section>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const deliveryDate = document.getElementById('fecha_entrega');
            if (deliveryDate) {
                const enforceMinDeliveryDate = () => {
                    if (deliveryDate.min && (!deliveryDate.value || deliveryDate.value < deliveryDate.min)) {
                        deliveryDate.value = deliveryDate.min;
                    }
                };
                deliveryDate.addEventListener('change', enforceMinDeliveryDate);
                enforceMinDeliveryDate();
            }

            const form = document.querySelector('[data-document-validation]');
            const paymentBox = document.querySelector('[data-payment-box]');
            const syncPayment = () => {
                const selected = document.querySelector('input[name="metodo_pago"]:checked')?.value || 'contra_entrega';
                document.querySelectorAll('[data-payment-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.paymentPanel !== selected);
                });
            };
            document.querySelectorAll('input[name="metodo_pago"]').forEach((input) => {
                input.addEventListener('change', syncPayment);
            });
            if (paymentBox) {
                syncPayment();
            }

            if (!form) return;

            const receipt = document.getElementById('comprobante_tipo');
            const type = document.getElementById('tipo_documento');
            const number = document.getElementById('numero_documento');
            const message = document.querySelector('[data-document-message]');
            const messageText = document.querySelector('[data-document-message-text]');
            const inlineName = document.querySelector('[data-document-inline-name]');
            const details = document.querySelector('[data-document-details]');
            const lookup = document.querySelector('[data-document-lookup]');
            const csrf = document.querySelector('input[name="_token"]')?.value || '';
            let lookupTimer = null;
            let lookupKey = '';

            const onlyDigits = (value) => value.replace(/\D+/g, '');
            const validRuc = (value) => {
                const ruc = onlyDigits(value);
                if (!/^\d{11}$/.test(ruc) || !['10', '15', '17', '20'].includes(ruc.slice(0, 2))) return false;
                const weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
                const sum = weights.reduce((acc, weight, index) => acc + Number(ruc[index]) * weight, 0);
                let check = 11 - (sum % 11);
                if (check === 10) check = 0;
                if (check === 11) check = 1;
                return check === Number(ruc[10]);
            };

            const setMessage = (text, state = 'neutral') => {
                messageText.textContent = text;
                message.classList.toggle('text-emerald-700', state === 'success');
                message.classList.toggle('text-red-600', state === 'error');
                message.classList.toggle('text-stone-600', state === 'neutral');
            };

            const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));

            const documentDisplayName = (payload) => {
                const data = payload?.data || {};
                if (type.value === 'DNI') {
                    const fullName = String(data.nombre_completo || '').trim();
                    if (fullName !== '') return fullName;
                    return [
                        data.first_name || data.nombres,
                        data.first_last_name || data.apellido_paterno,
                        data.second_last_name || data.apellido_materno,
                    ].filter(Boolean).join(' ').trim();
                }

                return String(data.razon_social || data.nombre_o_razon_social || data.nombre_comercial || '').trim();
            };

            const clearInlineName = () => {
                if (!inlineName) return;
                inlineName.textContent = '';
                inlineName.classList.add('hidden');
            };

            const setInlineName = (payload) => {
                if (!inlineName) return;
                const name = documentDisplayName(payload);
                if (name === '') {
                    clearInlineName();
                    return;
                }

                inlineName.textContent = `${type.value === 'DNI' ? 'Nombre' : 'Empresa'}: ${name}`;
                inlineName.classList.remove('hidden');
            };

            const clearDetails = () => {
                if (!details) return;
                details.innerHTML = '';
                details.classList.add('hidden');
            };

            const setDetails = (payload) => {
                if (!details) return;
                const data = payload?.data || {};
                const rows = type.value === 'DNI'
                    ? [
                        ['Nombres', data.first_name || data.nombres],
                        ['Apellido paterno', data.first_last_name || data.apellido_paterno],
                        ['Apellido materno', data.second_last_name || data.apellido_materno],
                    ]
                    : [
                        ['Razon social', data.razon_social || data.nombre_o_razon_social],
                        ['Nombre comercial', data.nombre_comercial],
                        ['Estado / condicion', [data.estado, data.condicion].filter(Boolean).join(' / ')],
                        ['Direccion', data.direccion],
                    ];
                const visibleRows = rows.filter(([, value]) => String(value || '').trim() !== '');

                if (visibleRows.length === 0) {
                    clearDetails();
                    return;
                }

                details.innerHTML = visibleRows.map(([label, value]) => (
                    `<div><span class="font-semibold">${label}:</span> ${escapeHtml(value)}</div>`
                )).join('');
                details.classList.remove('hidden');
            };

            const hasValidFormat = () => type.value === 'DNI'
                ? /^\d{8}$/.test(number.value)
                : validRuc(number.value);

            const sync = () => {
                if (receipt.value === 'factura') {
                    type.value = 'RUC';
                }

                number.value = onlyDigits(number.value).slice(0, type.value === 'RUC' ? 11 : 8);
                const ok = hasValidFormat();
                lookupKey = '';
                clearDetails();
                clearInlineName();

                setMessage(
                    ok
                        ? 'Formato correcto. Presiona Validar para consultar los datos.'
                        : (type.value === 'DNI'
                        ? 'El DNI debe tener exactamente 8 digitos.'
                        : 'El RUC debe tener 11 digitos y digito verificador correcto.'),
                    ok ? 'neutral' : 'error'
                );

                if (lookupTimer) {
                    clearTimeout(lookupTimer);
                }
            };

            const validateWithProvider = async () => {
                if (!hasValidFormat()) {
                    sync();
                    return;
                }

                const currentKey = `${type.value}:${number.value}`;
                if (currentKey === lookupKey) {
                    return;
                }

                lookupKey = currentKey;
                lookup.disabled = true;
                setMessage(`Validando ${type.value === 'DNI' ? 'DNI' : 'RUC'}...`);
                clearDetails();
                clearInlineName();

                try {
                    const response = await fetch(form.dataset.documentUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({
                            tipo_documento: type.value,
                            numero_documento: number.value,
                        }),
                    });
                    const payload = await response.json();
                    setMessage(
                        payload.message || (response.ok ? 'Documento validado correctamente.' : 'No se pudo validar el documento.'),
                        response.ok && payload.ok
                            ? (payload.validation_unavailable ? 'neutral' : 'success')
                            : 'error'
                    );
                    if (response.ok && payload.ok && !payload.validation_unavailable) {
                        setInlineName(payload);
                        setDetails(payload);
                    } else {
                        clearInlineName();
                        clearDetails();
                    }
                } catch (error) {
                    lookupKey = '';
                    clearInlineName();
                    clearDetails();
                    setMessage('No se pudo conectar con el servicio de validacion.', 'error');
                } finally {
                    lookup.disabled = false;
                }
            };

            receipt.addEventListener('change', sync);
            type.addEventListener('change', sync);
            number.addEventListener('input', sync);
            lookup.addEventListener('click', validateWithProvider);
            sync();
        });
    </script>
@endsection
