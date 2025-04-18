<?php

/**
 * The plugin module services.
 *
 * @package WooCommerce\PayPalCommerce
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce;

use Dhii\Versions\StringVersionFactory;
use WooCommerce\PayPalCommerce\Http\RedirectorInterface;
use WooCommerce\PayPalCommerce\Http\WpRedirector;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WpOop\WordPress\Plugin\PluginInterface;
return array('ppcp.plugin' => function (ContainerInterface $container): PluginInterface {
    $factory = new \WooCommerce\PayPalCommerce\FilePathPluginFactory(new StringVersionFactory());
    return $factory->createPluginFromFilePath(dirname(realpath(__FILE__), 2) . '/woocommerce-paypal-payments.php');
}, 'ppcp.asset-version' => function (ContainerInterface $container): string {
    $plugin = $container->get('ppcp.plugin');
    assert($plugin instanceof PluginInterface);
    return (string) $plugin->getVersion();
}, 'http.redirector' => function (ContainerInterface $container): RedirectorInterface {
    return new WpRedirector();
});
