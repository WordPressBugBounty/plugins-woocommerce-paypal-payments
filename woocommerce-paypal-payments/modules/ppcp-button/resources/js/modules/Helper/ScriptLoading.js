import dataClientIdAttributeHandler from '../DataClientIdAttributeHandler';
import { loadScript } from '@paypal/paypal-js';
import widgetBuilder from '../Renderer/WidgetBuilder';
import merge from 'deepmerge';
import { keysToCamelCase } from './Utils';
import { getCurrentPaymentMethod } from './CheckoutMethodState';
import { v4 as uuidv4 } from 'uuid';

// This component may be used by multiple modules. This assures that options are shared between all instances.
const scriptOptionsMap = {};

const getNamespaceOptions = ( namespace ) => {
	if ( ! scriptOptionsMap[ namespace ] ) {
		scriptOptionsMap[ namespace ] = {
			isLoading: false,
			onLoadedCallbacks: [],
			onErrorCallbacks: [],
		};
	}
	return scriptOptionsMap[ namespace ];
};

export const loadPaypalScript = ( config, onLoaded, onError = null ) => {
	const dataNamespace = config?.data_namespace || '';
	const options = getNamespaceOptions( dataNamespace );

	// If PayPal is already loaded for this namespace, call the onLoaded callback and return.
	if ( typeof window.paypal !== 'undefined' && ! dataNamespace ) {
		onLoaded();
		return;
	}

	// Add the onLoaded callback to the onLoadedCallbacks stack.
	options.onLoadedCallbacks.push( onLoaded );
	if ( onError ) {
		options.onErrorCallbacks.push( onError );
	}

	// Return if it's still loading.
	if ( options.isLoading ) {
		return;
	}
	options.isLoading = true;

	const resetState = () => {
		options.isLoading = false;
		options.onLoadedCallbacks = [];
		options.onErrorCallbacks = [];
	};

	// Callback to be called once the PayPal script is loaded.
	const callback = ( paypal ) => {
		widgetBuilder.setPaypal( paypal );

		for ( const onLoadedCallback of options.onLoadedCallbacks ) {
			onLoadedCallback();
		}

		resetState();
	};
	const errorCallback = ( err ) => {
		for ( const onErrorCallback of options.onErrorCallbacks ) {
			onErrorCallback( err );
		}

		resetState();
	};

	// Build the PayPal script options.
	let scriptOptions = keysToCamelCase( config.url_params );
	if ( config.script_attributes ) {
		scriptOptions = merge( scriptOptions, config.script_attributes );
	}

	// Axo SDK options
	const sdkClientToken = config?.axo?.sdk_client_token;
	const uuid = uuidv4().replace( /-/g, '' );
	if ( sdkClientToken && config?.user?.is_logged !== true ) {
		scriptOptions[ 'data-sdk-client-token' ] = sdkClientToken;
		scriptOptions[ 'data-client-metadata-id' ] = uuid;
	}

	// Load PayPal script for special case with data-client-token
	if (
		config.data_client_id?.set_attribute &&
		config.vault_v3_enabled !== '1'
	) {
		dataClientIdAttributeHandler(
			scriptOptions,
			config.data_client_id,
			callback,
			errorCallback
		);
		return;
	}

	// Adds data-user-id-token to script options.
	const userIdToken = config?.save_payment_methods?.id_token;
	if ( userIdToken && config?.user?.is_logged === true ) {
		scriptOptions[ 'data-user-id-token' ] = userIdToken;
	}

	// Adds data-namespace to script options.
	if ( dataNamespace ) {
		scriptOptions.dataNamespace = dataNamespace;
	}

	// Load PayPal script
	loadScript( scriptOptions ).then( callback ).catch( errorCallback );
};

export const loadPaypalScriptPromise = ( config ) => {
	return new Promise( ( resolve, reject ) => {
		loadPaypalScript( config, resolve, reject );
	} );
};

export const loadPaypalJsScript = ( options, buttons, container ) => {
	loadScript( options ).then( ( paypal ) => {
		paypal.Buttons( buttons ).render( container );
	} );
};

export const loadPaypalJsScriptPromise = ( options ) => {
	return new Promise( ( resolve, reject ) => {
		loadScript( options ).then( resolve ).catch( reject );
	} );
};
