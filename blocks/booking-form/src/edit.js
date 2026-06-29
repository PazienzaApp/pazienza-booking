import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    ToggleControl,
    SelectControl,
    TextareaControl,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
    const {
        defaultServiceId,
        defaultResourceId,
        allowServiceSelect,
        allowResourceSelect,
        calendarMode,
        customFields,
    } = attributes;

    const blockProps = useBlockProps( {
        className: 'pazienza-booking pazienza-booking--editor-preview',
    } );

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Servizio e risorsa', 'pazienza-booking' ) }>
                    <ToggleControl
                        label={ __( 'Permetti selezione servizio', 'pazienza-booking' ) }
                        checked={ allowServiceSelect }
                        onChange={ ( val ) => setAttributes( { allowServiceSelect: val } ) }
                        help={ __( 'Se disabilitato, imposta un servizio predefinito.', 'pazienza-booking' ) }
                    />
                    { ! allowServiceSelect && (
                        <TextControl
                            label={ __( 'ID servizio predefinito', 'pazienza-booking' ) }
                            value={ defaultServiceId }
                            onChange={ ( val ) => setAttributes( { defaultServiceId: val } ) }
                            placeholder="00000000-0000-0000-0000-000000000000"
                        />
                    ) }
                    <ToggleControl
                        label={ __( 'Permetti selezione risorsa/operatore', 'pazienza-booking' ) }
                        checked={ allowResourceSelect }
                        onChange={ ( val ) => setAttributes( { allowResourceSelect: val } ) }
                        help={ __( 'Se disabilitato, imposta una risorsa predefinita o la prima disponibile.', 'pazienza-booking' ) }
                    />
                    { ! allowResourceSelect && (
                        <TextControl
                            label={ __( 'ID risorsa predefinita', 'pazienza-booking' ) }
                            value={ defaultResourceId }
                            onChange={ ( val ) => setAttributes( { defaultResourceId: val } ) }
                            placeholder="00000000-0000-0000-0000-000000000000"
                        />
                    ) }
                </PanelBody>

                <PanelBody title={ __( 'Calendario', 'pazienza-booking' ) } initialOpen={ false }>
                    <SelectControl
                        label={ __( 'Modalità calendario', 'pazienza-booking' ) }
                        value={ calendarMode }
                        options={ [
                            { label: __( 'Completo (griglia mese)', 'pazienza-booking' ), value: 'full' },
                            { label: __( 'Compatto (lista date)', 'pazienza-booking' ),   value: 'compact' },
                        ] }
                        onChange={ ( val ) => setAttributes( { calendarMode: val } ) }
                    />
                </PanelBody>

                <PanelBody title={ __( 'Campi personalizzati', 'pazienza-booking' ) } initialOpen={ false }>
                    <TextareaControl
                        label={ __( 'Definizione campi (JSON)', 'pazienza-booking' ) }
                        value={ customFields }
                        onChange={ ( val ) => setAttributes( { customFields: val } ) }
                        rows={ 8 }
                        help={ __(
                            'Array JSON con oggetti { id, label, type, options, required }. '
                            + 'Tipi: text, textarea, select, radio, checkbox.',
                            'pazienza-booking'
                        ) }
                    />
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                <div className="pazienza-booking__editor-placeholder">
                    <span className="dashicons dashicons-calendar-alt" style={ { fontSize: 32, marginRight: 8 } } />
                    <span>{ __( 'Form prenotazione Pazienza', 'pazienza-booking' ) }</span>
                </div>
            </div>
        </>
    );
}
