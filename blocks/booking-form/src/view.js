import { createElement, useState, useEffect, useCallback, useMemo, Fragment } from '@wordpress/element';
import { createRoot } from '@wordpress/element';

const { __ } = window.wp?.i18n ?? { __: ( s ) => s };

const STEP_SERVICE  = 'service';
const STEP_RESOURCE = 'resource';
const STEP_DATE     = 'date';
const STEP_CUSTOMER = 'customer';
const STEP_CONFIRM  = 'confirm';

// ── API helpers ───────────────────────────────────────────────────────────────

const cfg = () => window.pazienzaBookingConfig ?? {};

async function apiFetch( path, opts = {} ) {
    const base = cfg().restUrl ?? '/wp-json/pazienza-booking/v1';
    const [ routePart, queryPart ] = path.split( '?' );
    const baseWithRoute = base.replace( /\/$/, '' ) + routePart;
    const url = queryPart
        ? baseWithRoute + ( baseWithRoute.includes( '?' ) ? '&' : '?' ) + queryPart
        : baseWithRoute;
    const res  = await fetch( url, {
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce':   cfg().nonce ?? '',
            ...( opts.headers ?? {} ),
        },
        ...opts,
    } );
    if ( ! res.ok ) {
        const err = await res.json().catch( () => ( {} ) );
        throw new Error( err.message ?? err.data ?? `HTTP ${ res.status }` );
    }
    if ( res.status === 204 ) return null;
    return res.json();
}

// ── Utility ───────────────────────────────────────────────────────────────────

function formatDate( iso ) {
    if ( ! iso ) return '';
    const d = new Date( iso );
    return d.toLocaleDateString( document.documentElement.lang || 'it-IT', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    } );
}

function formatTime( iso ) {
    if ( ! iso ) return '';
    return new Date( iso ).toLocaleTimeString( document.documentElement.lang || 'it-IT', {
        hour: '2-digit', minute: '2-digit', hour12: false,
    } );
}

// Restituisce la data locale (YYYY-MM-DD) del Date passato, senza conversione UTC.
function isoDate( date ) {
    const y = date.getFullYear();
    const m = String( date.getMonth() + 1 ).padStart( 2, '0' );
    const d = String( date.getDate() ).padStart( 2, '0' );
    return `${ y }-${ m }-${ d }`;
}

// Days that have at least one slot (using local date, not UTC).
function daysWithSlots( slots ) {
    const set = new Set();
    slots.forEach( ( s ) => set.add( isoDate( new Date( s.start ) ) ) );
    return set;
}

// ── Step components ───────────────────────────────────────────────────────────

function ServiceStep( { services, onSelect, loading } ) {
    if ( loading ) return createElement( Spinner, null );
    if ( ! services.length ) {
        return createElement( 'p', { className: 'pazienza-booking__empty' },
            __( 'Nessun servizio disponibile.', 'pazienza-booking' ) );
    }
    return createElement( 'div', { className: 'pazienza-booking__service-list' },
        services.map( ( svc ) =>
            createElement( 'button', {
                key:       svc.id,
                className: 'pazienza-booking__service-card',
                onClick:   () => onSelect( svc ),
            },
                createElement( 'strong', null, svc.name ),
                svc.defaultDurationMinutes
                    ? createElement( 'span', null, svc.defaultDurationMinutes + ' min' )
                    : null,
                svc.description
                    ? createElement( 'p', null, svc.description )
                    : null
            )
        )
    );
}

function ResourceStep( { resources, onSelect, onSkip, loading } ) {
    if ( loading ) return createElement( Spinner, null );
    return createElement( 'div', { className: 'pazienza-booking__resource-list' },
        createElement( 'button', {
            className: 'pazienza-booking__resource-card pazienza-booking__resource-card--any',
            onClick:   () => onSkip(),
        }, __( 'Prima disponibile', 'pazienza-booking' ) ),
        resources.map( ( r ) =>
            createElement( 'button', {
                key:       r.id,
                className: 'pazienza-booking__resource-card',
                onClick:   () => onSelect( r ),
            }, r.name )
        )
    );
}

function CalendarStep( { slots, loading, calendarMode, onSlotSelect } ) {
    const today = useMemo( () => new Date(), [] );
    const [ month, setMonth ] = useState( () => {
        const d = new Date();
        d.setDate( 1 );
        return d;
    } );
    const [ selectedDay, setSelectedDay ] = useState( null );

    const available = useMemo( () => daysWithSlots( slots ), [ slots ] );

    const slotsForDay = useMemo( () => {
        if ( ! selectedDay ) return [];
        return slots.filter( ( s ) => isoDate( new Date( s.start ) ) === selectedDay );
    }, [ slots, selectedDay ] );

    if ( loading ) return createElement( Spinner, null );

    if ( calendarMode === 'compact' ) {
        const days = [ ...available ].sort();
        return createElement( 'div', { className: 'pazienza-booking__compact-calendar' },
            days.map( ( day ) =>
                createElement( 'div', {
                    key:       day,
                    className: 'pazienza-booking__calendar-day pazienza-booking__calendar-day--compact'
                              + ( selectedDay === day ? ' pazienza-booking__calendar-day--selected' : '' ),
                    onClick:   () => setSelectedDay( day ),
                }, formatDate( day + 'T12:00:00' ) )
            ),
            selectedDay && createElement( SlotList, { slots: slotsForDay, onSelect: onSlotSelect } )
        );
    }

    // Full grid mode.
    const year      = month.getFullYear();
    const m         = month.getMonth();
    const firstDay  = new Date( year, m, 1 ).getDay(); // 0=Sun
    const daysInMonth = new Date( year, m + 1, 0 ).getDate();
    const prevMonth = () => setMonth( new Date( year, m - 1, 1 ) );
    const nextMonth = () => setMonth( new Date( year, m + 1, 1 ) );

    const cells = [];
    // Blank cells before first day (Mon-based).
    const startOffset = ( firstDay + 6 ) % 7;
    for ( let i = 0; i < startOffset; i++ ) {
        cells.push( createElement( 'div', { key: 'b' + i, className: 'pazienza-booking__calendar-cell pazienza-booking__calendar-cell--empty' } ) );
    }
    for ( let d = 1; d <= daysInMonth; d++ ) {
        const dayStr  = isoDate( new Date( year, m, d ) );
        const hasSlot = available.has( dayStr );
        const isPast  = new Date( year, m, d ) < today;
        const isSel   = selectedDay === dayStr;
        cells.push(
            createElement( 'div', {
                key:       dayStr,
                className: [
                    'pazienza-booking__calendar-cell',
                    hasSlot  ? 'pazienza-booking__calendar-cell--available' : '',
                    isPast   ? 'pazienza-booking__calendar-cell--past'      : '',
                    isSel    ? 'pazienza-booking__calendar-cell--selected'  : '',
                ].join( ' ' ).trim(),
                onClick:   hasSlot && ! isPast ? () => setSelectedDay( dayStr ) : undefined,
            }, d )
        );
    }

    const monthLabel = month.toLocaleDateString( document.documentElement.lang || 'it-IT', {
        month: 'long', year: 'numeric',
    } );

    return createElement( 'div', { className: 'pazienza-booking__calendar' },
        createElement( 'div', { className: 'pazienza-booking__calendar-nav' },
            createElement( 'button', { className: 'pazienza-booking__calendar-prev', onClick: prevMonth }, '‹' ),
            createElement( 'span',   { className: 'pazienza-booking__calendar-month' }, monthLabel ),
            createElement( 'button', { className: 'pazienza-booking__calendar-next', onClick: nextMonth }, '›' )
        ),
        createElement( 'div', { className: 'pazienza-booking__calendar-weekdays' },
            [ 'Lu', 'Ma', 'Me', 'Gi', 'Ve', 'Sa', 'Do' ].map( ( d ) =>
                createElement( 'div', { key: d, className: 'pazienza-booking__calendar-weekday' }, d )
            )
        ),
        createElement( 'div', { className: 'pazienza-booking__calendar-grid' }, ...cells ),
        selectedDay && createElement( SlotList, { slots: slotsForDay, onSelect: onSlotSelect } )
    );
}

function SlotList( { slots, onSelect } ) {
    if ( ! slots.length ) {
        return createElement( 'p', { className: 'pazienza-booking__empty' },
            __( 'Nessuno slot disponibile per questo giorno.', 'pazienza-booking' ) );
    }
    return createElement( 'div', { className: 'pazienza-booking__slot-list' },
        slots.map( ( slot ) =>
            createElement( 'button', {
                key:       slot.start,
                className: 'pazienza-booking__slot',
                onClick:   () => onSelect( slot ),
            },
                formatTime( slot.start ) + ' – ' + formatTime( slot.end ),
                slot.resourceName
                    ? createElement( 'small', null, slot.resourceName )
                    : null
            )
        )
    );
}

function CustomerStep( { customFieldDefs, onSubmit, loading, error } ) {
    const isLoggedIn = cfg().isLoggedIn ?? false;
    const savedName  = cfg().userName   ?? '';
    const savedEmail = cfg().userEmail  ?? '';

    const [ form, setForm ]             = useState( { customer_name: savedName, customer_email: savedEmail } );
    const [ registerAccount, setRegisterAccount ] = useState( false );

    const update      = ( k ) => ( e ) => setForm( ( f ) => ( { ...f, [ k ]: e.target.value } ) );
    const updateCheck = ( k ) => ( e ) => setForm( ( f ) => ( { ...f, [ k ]: e.target.checked ? '1' : '' } ) );

    const handleSubmit = ( e ) => {
        e.preventDefault();
        onSubmit( { ...form, register_account: registerAccount } );
    };

    const nameReadonly  = isLoggedIn && !! savedName;
    const emailReadonly = isLoggedIn;

    return createElement( 'form', { className: 'pazienza-booking__form', onSubmit: handleSubmit },
        createElement( Field, { label: __( 'Nome e cognome *', 'pazienza-booking' ) },
            createElement( 'input', {
                type:      'text',
                name:      'customer_name',
                required:  true,
                readOnly:  nameReadonly,
                value:     form.customer_name ?? '',
                className: nameReadonly ? 'pazienza-booking__input--readonly' : '',
                onChange:  nameReadonly ? () => {} : update( 'customer_name' ),
            } )
        ),
        createElement( Field, { label: __( 'Email *', 'pazienza-booking' ) },
            createElement( 'input', {
                type:      'email',
                name:      'customer_email',
                required:  true,
                readOnly:  emailReadonly,
                value:     form.customer_email ?? '',
                className: emailReadonly ? 'pazienza-booking__input--readonly' : '',
                onChange:  emailReadonly ? () => {} : update( 'customer_email' ),
            } )
        ),
        createElement( Field, { label: __( 'Telefono', 'pazienza-booking' ) },
            createElement( 'input', { type: 'tel', name: 'customer_phone', onChange: update( 'customer_phone' ) } )
        ),
        ...customFieldDefs.map( ( f ) => renderCustomField( f, form, update, updateCheck ) ),
        ! isLoggedIn && createElement( Field, { label: '' },
            createElement( 'label', { className: 'pazienza-booking__checkbox-label' },
                createElement( 'input', {
                    type:     'checkbox',
                    checked:  registerAccount,
                    onChange: ( e ) => setRegisterAccount( e.target.checked ),
                } ),
                ' ',
                __( 'Crea un account per gestire le tue prenotazioni', 'pazienza-booking' )
            )
        ),
        error && createElement( 'p', { className: 'pazienza-booking__error' }, error ),
        createElement( 'button', {
            type:      'submit',
            className: 'pazienza-booking__submit',
            disabled:  loading,
        }, loading ? __( 'Invio in corso…', 'pazienza-booking' ) : __( 'Conferma prenotazione', 'pazienza-booking' ) )
    );
}

function renderCustomField( f, form, update, updateCheck ) {
    const id  = 'pbf-' + f.id;
    const val = form[ f.id ] ?? '';

    if ( f.type === 'select' ) {
        const opts = ( f.options ?? '' ).split( '\n' ).map( ( o ) => o.trim() ).filter( Boolean );
        return createElement( Field, { key: f.id, label: f.label + ( f.required ? ' *' : '' ) },
            createElement( 'select', { name: f.id, required: f.required, onChange: update( f.id ) },
                createElement( 'option', { value: '' }, '—' ),
                opts.map( ( o ) => createElement( 'option', { key: o, value: o }, o ) )
            )
        );
    }

    if ( f.type === 'radio' ) {
        const opts = ( f.options ?? '' ).split( '\n' ).map( ( o ) => o.trim() ).filter( Boolean );
        return createElement( Field, { key: f.id, label: f.label + ( f.required ? ' *' : '' ) },
            opts.map( ( o ) =>
                createElement( 'label', { key: o, className: 'pazienza-booking__radio-label' },
                    createElement( 'input', {
                        type: 'radio', name: f.id, value: o, required: f.required,
                        onChange: update( f.id ),
                    } ),
                    ' ', o
                )
            )
        );
    }

    if ( f.type === 'checkbox' ) {
        return createElement( Field, { key: f.id, label: '' },
            createElement( 'label', { className: 'pazienza-booking__checkbox-label' },
                createElement( 'input', {
                    type: 'checkbox', name: f.id, required: f.required,
                    onChange: updateCheck( f.id ),
                } ),
                ' ', f.label + ( f.required ? ' *' : '' )
            )
        );
    }

    if ( f.type === 'textarea' ) {
        return createElement( Field, { key: f.id, label: f.label + ( f.required ? ' *' : '' ) },
            createElement( 'textarea', { name: f.id, required: f.required, rows: 3, onChange: update( f.id ) } )
        );
    }

    // Default: text
    return createElement( Field, { key: f.id, label: f.label + ( f.required ? ' *' : '' ) },
        createElement( 'input', { type: 'text', name: f.id, required: f.required, onChange: update( f.id ) } )
    );
}

function Field( { label, children } ) {
    return createElement( 'div', { className: 'pazienza-booking__field' },
        label ? createElement( 'label', { className: 'pazienza-booking__label' }, label ) : null,
        children
    );
}

function Spinner() {
    return createElement( 'div', { className: 'pazienza-booking__spinner' },
        createElement( 'span', { className: 'pazienza-booking__spinner-icon' } )
    );
}

const REGISTRATION_NOTES = {
    success:        __( 'Riceverai un\'email per impostare la password del tuo account.', 'pazienza-booking' ),
    already_exists: __( 'Esiste già un account con questa email: accedi per gestire le tue prenotazioni.', 'pazienza-booking' ),
};

function ConfirmStep( { slot, service, resource, successMessage, registrationResult } ) {
    return createElement( 'div', { className: 'pazienza-booking__confirmation' },
        createElement( 'div', { className: 'pazienza-booking__confirmation-icon' }, '✓' ),
        createElement( 'h3', null, __( 'Prenotazione confermata!', 'pazienza-booking' ) ),
        successMessage
            ? createElement( 'p', null, successMessage )
            : null,
        registrationResult && REGISTRATION_NOTES[ registrationResult ]
            ? createElement( 'p', { className: 'pazienza-booking__registration-note' },
                REGISTRATION_NOTES[ registrationResult ] )
            : null,
        createElement( 'dl', { className: 'pazienza-booking__confirmation-details' },
            createElement( 'dt', null, __( 'Servizio', 'pazienza-booking' ) ),
            createElement( 'dd', null, service?.name ?? '' ),
            slot?.resourceName
                ? Fragment.prototype === Fragment
                    ? [ createElement( 'dt', { key: 'rk' }, __( 'Con', 'pazienza-booking' ) ),
                        createElement( 'dd', { key: 'rv' }, slot.resourceName ) ]
                    : [ createElement( 'dt', { key: 'rk' }, __( 'Con', 'pazienza-booking' ) ),
                        createElement( 'dd', { key: 'rv' }, slot.resourceName ) ]
                : null,
            createElement( 'dt', null, __( 'Data', 'pazienza-booking' ) ),
            createElement( 'dd', null, formatDate( slot?.start ) ),
            createElement( 'dt', null, __( 'Orario', 'pazienza-booking' ) ),
            createElement( 'dd', null, formatTime( slot?.start ) + ' – ' + formatTime( slot?.end ) )
        )
    );
}

// ── Progress bar ──────────────────────────────────────────────────────────────

const STEPS = [ STEP_SERVICE, STEP_RESOURCE, STEP_DATE, STEP_CUSTOMER, STEP_CONFIRM ];

function ProgressBar( { step, allowServiceSelect, allowResourceSelect } ) {
    const labels = {
        [ STEP_SERVICE  ]: __( 'Servizio',  'pazienza-booking' ),
        [ STEP_RESOURCE ]: __( 'Operatore', 'pazienza-booking' ),
        [ STEP_DATE     ]: __( 'Data',      'pazienza-booking' ),
        [ STEP_CUSTOMER ]: __( 'Dati',      'pazienza-booking' ),
        [ STEP_CONFIRM  ]: __( 'Conferma',  'pazienza-booking' ),
    };

    const visible = STEPS.filter( ( s ) => {
        if ( s === STEP_SERVICE  && ! allowServiceSelect  ) return false;
        if ( s === STEP_RESOURCE && ! allowResourceSelect ) return false;
        return true;
    } );

    const currentIdx = visible.indexOf( step );

    return createElement( 'ol', { className: 'pazienza-booking__progress' },
        visible.map( ( s, i ) =>
            createElement( 'li', {
                key:       s,
                className: 'pazienza-booking__progress-step'
                          + ( i < currentIdx  ? ' pazienza-booking__progress-step--done'    : '' )
                          + ( i === currentIdx ? ' pazienza-booking__progress-step--active'  : '' ),
            }, labels[ s ] )
        )
    );
}

// ── Root app ──────────────────────────────────────────────────────────────────

function BookingApp( { attributes, successMessage, customFieldDefs } ) {
    const {
        defaultServiceId    = '',
        defaultResourceId   = '',
        allowServiceSelect  = true,
        allowResourceSelect = true,
        calendarMode        = 'full',
    } = attributes;

    const initialStep = defaultServiceId && ! allowServiceSelect
        ? ( defaultResourceId && ! allowResourceSelect ? STEP_DATE : STEP_RESOURCE )
        : STEP_SERVICE;

    const [ step,     setStep     ] = useState( initialStep );
    const [ services, setServices ] = useState( [] );
    const [ resources, setResources ] = useState( [] );
    const [ slots,    setSlots    ] = useState( [] );
    const [ selService,  setSelService  ] = useState( null );
    const [ selResource, setSelResource ] = useState( null );
    const [ selSlot,     setSelSlot     ] = useState( null );
    const [ loading,            setLoading            ] = useState( false );
    const [ error,              setError              ] = useState( null );
    const [ result,             setResult             ] = useState( null );
    const [ registrationResult, setRegistrationResult ] = useState( null );

    // Load services.
    useEffect( () => {
        if ( ! allowServiceSelect && defaultServiceId ) {
            setSelService( { id: defaultServiceId } );
            return;
        }
        setLoading( true );
        apiFetch( '/services' )
            .then( setServices )
            .catch( ( e ) => setError( e.message ) )
            .finally( () => setLoading( false ) );
    }, [] );

    // Load resources when service is selected.
    useEffect( () => {
        if ( ! selService ) return;
        if ( ! allowResourceSelect && defaultResourceId ) {
            setSelResource( { id: defaultResourceId } );
            return;
        }
        setLoading( true );
        apiFetch( '/resources?service_id=' + selService.id )
            .then( ( data ) => {
                setResources( data );
                if ( data.length === 0 ) {
                    loadSlots( undefined );
                    setStep( STEP_DATE );
                }
            } )
            .catch( ( e ) => setError( e.message ) )
            .finally( () => setLoading( false ) );
    }, [ selService ] );

    // Load slots when resource (or skip) is known.
    const loadSlots = useCallback( ( resourceId ) => {
        const today = new Date();
        const to    = new Date();
        to.setDate( to.getDate() + 30 );
        const params = new URLSearchParams( {
            service_id: selService?.id ?? defaultServiceId,
            from:       isoDate( today ),
            to:         isoDate( to ),
            ...( resourceId ? { resource_id: resourceId } : {} ),
        } );
        setLoading( true );
        apiFetch( '/slots?' + params )
            .then( setSlots )
            .catch( ( e ) => setError( e.message ) )
            .finally( () => setLoading( false ) );
    }, [ selService, defaultServiceId ] );

    // ── Handlers ──────────────────────────────────────────────────────────────

    const onSelectService = ( svc ) => {
        setSelService( svc );
        setLoading( true );
        setStep( allowResourceSelect ? STEP_RESOURCE : STEP_DATE );
        if ( ! allowResourceSelect ) loadSlots( defaultResourceId || undefined );
    };

    const onSelectResource = ( res ) => {
        setSelResource( res );
        loadSlots( res.id );
        setStep( STEP_DATE );
    };

    const onSkipResource = () => {
        loadSlots( undefined );
        setStep( STEP_DATE );
    };

    const onSelectSlot = ( slot ) => {
        setSelSlot( slot );
        setStep( STEP_CUSTOMER );
    };

    const onSubmitCustomer = ( form ) => {
        setLoading( true );
        setError( null );
        apiFetch( '/appointments', {
            method: 'POST',
            body:   JSON.stringify( {
                service_id:       selService?.id ?? defaultServiceId,
                resource_id:      selSlot?.resourceId ?? selResource?.id ?? defaultResourceId,
                start:            selSlot?.start,
                end:              selSlot?.end,
                customer_name:    form.customer_name,
                customer_email:   form.customer_email,
                customer_phone:   form.customer_phone ?? '',
                notes:            form.notes ?? '',
                register_account: form.register_account ?? false,
                custom_fields:    Object.fromEntries(
                    customFieldDefs
                        .filter( ( f ) => form[ f.id ] !== undefined )
                        .map( ( f ) => [ f.id, form[ f.id ] ] )
                ),
            } ),
        } )
        .then( ( r ) => {
            setRegistrationResult( r.registration_result ?? null );
            setResult( r );
            setStep( STEP_CONFIRM );
        } )
        .catch( ( e ) => setError( e.message ) )
        .finally( () => setLoading( false ) );
    };

    // ── Render ────────────────────────────────────────────────────────────────

    return createElement( 'div', { className: 'pazienza-booking__inner' },
        step !== STEP_CONFIRM && createElement( ProgressBar, {
            step, allowServiceSelect, allowResourceSelect,
        } ),

        step === STEP_SERVICE && createElement( ServiceStep, {
            services, loading, onSelect: onSelectService,
        } ),

        step === STEP_RESOURCE && createElement( ResourceStep, {
            resources, loading,
            onSelect: onSelectResource,
            onSkip:   onSkipResource,
        } ),

        step === STEP_DATE && createElement( CalendarStep, {
            slots, loading, calendarMode, onSlotSelect: onSelectSlot,
        } ),

        step === STEP_CUSTOMER && createElement( CustomerStep, {
            customFieldDefs, loading, error, onSubmit: onSubmitCustomer,
        } ),

        step === STEP_CONFIRM && createElement( ConfirmStep, {
            slot: selSlot, service: selService, resource: selResource,
            successMessage, registrationResult,
        } ),

        step !== STEP_SERVICE && step !== STEP_CONFIRM && createElement( 'button', {
            className: 'pazienza-booking__back',
            onClick:   () => {
                const order = [ STEP_SERVICE, STEP_RESOURCE, STEP_DATE, STEP_CUSTOMER ];
                const prev  = order[ order.indexOf( step ) - 1 ];
                if ( prev ) setStep( prev );
            },
        }, '← ' + __( 'Indietro', 'pazienza-booking' ) )
    );
}

// ── Mount ─────────────────────────────────────────────────────────────────────

document.querySelectorAll( '.pazienza-booking[data-attributes]' ).forEach( ( el ) => {
    let attributes = {};
    try { attributes = JSON.parse( el.dataset.attributes ); } catch ( _ ) {}

    const successMessage = window.pazienzaBookingConfig?.successMessage ?? '';
    let customFieldDefs  = [];
    try { customFieldDefs = JSON.parse( attributes.customFields ?? '[]' ); } catch ( _ ) {}

    // Merge plugin-level custom fields (set in WP admin) with block-level overrides.
    if ( ! customFieldDefs.length && window.pazienzaBookingConfig?.customFields ) {
        try { customFieldDefs = JSON.parse( window.pazienzaBookingConfig.customFields ); } catch ( _ ) {}
    }

    const root = createRoot( el );
    root.render(
        createElement( BookingApp, { attributes, successMessage, customFieldDefs } )
    );
} );
