<?php
/**
 * Plugin Name:       WooCommerce BuyAdwiser XML Feed Generator
 * Plugin URI:        https://github.com/BuyAdwiser/supplier-export-woocomerce
 * Description:       Generates a basic WooCommerce product XML feed intended for BuyAdwiser. Access feed via: ?generate_buyadwiser_feed=true
 * Version:           1.0.0
 * Author:            BuyAdwiser
 * Author URI:        https://visosnuolaidos.lt
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wc-buyadwiser-feed
 * Domain Path:       /languages
 * WC requires at least: 3.0
 * WC tested up to:   9.0
 * HPOS compatible: true
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if WooCommerce is active before proceeding.
 */
add_action( 'plugins_loaded', 'wc_buyadwiser_feed_init' );

function wc_buyadwiser_feed_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wc_buyadwiser_feed_missing_wc_notice' );
        return; // Stop if WooCommerce is not active
    }

    // Add the action hook to listen for the feed request
    add_action('init', 'generate_buyadwiser_xml_feed');
}

/**
 * Admin notice if WooCommerce is missing.
 */
function wc_buyadwiser_feed_missing_wc_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'BuyAdwiser XML Feed Generator requires WooCommerce to be installed and active.', 'wc-buyadwiser-feed' ); ?></p>
    </div>
    <?php
}


/**
 * Generate BuyAdwiser XML Feed
 *
 * ================== VERY IMPORTANT DISCLAIMERS ==================
 * 1.  **PERFORMANCE:** For large stores (>1000 products), generating this feed
 * on every request (?generate_buyadwiser_feed=true) can be slow and resource-
 * intensive. Consider implementing WP-Cron to generate a static XML file
 * periodically for better performance.
 * 2.  **ERROR HANDLING:** This sample has minimal error checking. A production-
 * ready version needs more robust validation and error handling.
 * ================================================================
 *
 * Access this feed by visiting yourdomain.com/?generate_buyadwiser_feed=true
 */
function generate_buyadwiser_xml_feed() {
    // Check if our specific query parameter is set
    if (isset($_GET['generate_buyadwiser_feed']) && $_GET['generate_buyadwiser_feed'] === 'true') {

        // Set the header before any output
        header('Content-Type: application/xml; charset=utf-8');

        // 1. !! GET BuyAdwiser's XML spec AND ADJUST XML STRUCTURE BELOW !!
        // Example structure assumed: <products><product>...</product></products>
        // Using the helper class to ensure addCData is available
        $xml = new SimpleXMLElementExtendedBuyAdwiser('<?xml version="1.0" encoding="UTF-8"?><products></products>');

        // 2. Define Arguments for Product Query
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1, // Get all products. Use a smaller number (e.g., 50) for testing.
            'post_status'    => 'publish', // Only published products
            'tax_query' => array(
                 array(
                     'taxonomy' => 'product_visibility',
                     'field'    => 'name',
                     'terms'    => 'exclude-from-catalog', // Exclude hidden products
                     'operator' => 'NOT IN',
                 ),
            ),
            // Add more filters if needed (e.g., specific categories)
        );
        $products_query = new WP_Query($args);

        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                global $product;

                // Ensure $product is a valid WC_Product object
                if (!is_object($product) || ! $product instanceof WC_Product) {
                    continue;
                }

                // === Logic for Handling Variable Products (Basic Parent Output) ===
                // To export variations as separate items (often required), you'd need to:
                // 1. Check if $product->is_type('variable').
                // 2. If yes, use $product->get_available_variations() or similar.
                // 3. Loop through each variation.
                // 4. Create a *separate* <product> node for each variation.
                // 5. Populate variation node with variation-specific data (SKU, price, stock, attributes, image).
                // This sample currently proceeds with the parent product data.

                // Skip product if it doesn't have a price (unless variable, where variations might have prices)
                if ( !$product->get_price() && !$product->is_type('variable') ) {
                     continue;
                }

                // 3. === START XML NODE FOR ONE PRODUCT (ADAPT THIS SECTION!) ===
                $product_node = $xml->addChild('product');

                // --- Core Identification ---
                $product_node->addChild('internal_id', $product->get_id()); // WooCommerce internal Post ID
                $sku = $product->get_sku();
                if (!empty($sku)) {
                    $product_node->addChild('sku', esc_attr($sku)); // Use SKU if available
                }
                $product_node->addChild('product_type', esc_attr($product->get_type())); // simple, variable, grouped, external

                // --- Basic Information ---
                $product_node->addChild('name')->addCData( $product->get_name() );
                $product_node->addChild('url', esc_url(get_permalink($product->get_id())) );
                // Use long description, strip tags, use CDATA
                $product_node->addChild('description')->addCData( wp_strip_all_tags($product->get_description()) );
                // Short description might also be useful
                $short_desc = $product->get_short_description();
                if ($short_desc) {
                    $product_node->addChild('short_description')->addCData( wp_strip_all_tags($short_desc) );
                }

                // --- Pricing ---
                $product_node->addChild('regular_price', esc_attr(wc_format_decimal($product->get_regular_price(), 2)) );
                $product_node->addChild('sale_price', esc_attr(wc_format_decimal($product->get_sale_price(), 2)) );
                // Use wc_get_price_to_display() for the final price shown to customer (includes tax based on settings)
                $display_price = wc_get_price_to_display($product);
                $product_node->addChild('price', esc_attr(wc_format_decimal($display_price, 2)) );
                $product_node->addChild('on_sale', $product->is_on_sale() ? 'true' : 'false');
                // Sale dates (if needed)
                $sale_start = $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->date('Y-m-d') : '';
                $sale_end = $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date('Y-m-d') : '';
                if ($sale_start) { $product_node->addChild('sale_price_effective_date_start', $sale_start); }
                if ($sale_end) { $product_node->addChild('sale_price_effective_date_end', $sale_end); }
                $product_node->addChild('tax_status', esc_attr($product->get_tax_status())); // taxable, shipping, none
                $product_node->addChild('tax_class', esc_attr($product->get_tax_class()));

                // --- Inventory ---
                $product_node->addChild('stock_status', esc_attr($product->get_stock_status())); // instock, outofstock, onbackorder
                $product_node->addChild('manage_stock', $product->get_manage_stock() ? 'true' : 'false');
                if ($product->get_manage_stock()) {
                    $product_node->addChild('stock_quantity', intval($product->get_stock_quantity()));
                }
                $product_node->addChild('backorders_allowed', $product->backorders_allowed() ? 'true' : 'false'); // 'yes', 'no', 'notify'
                $product_node->addChild('sold_individually', $product->is_sold_individually() ? 'true' : 'false');

                // --- Shipping ---
                $product_node->addChild('weight_kg', esc_attr($product->get_weight())); // Assumes store unit is kg, adjust if needed
                $dimensions = $product->get_dimensions(false); // Get as array
                if (!empty($dimensions['length'])) { $product_node->addChild('length_cm', esc_attr($dimensions['length'])); }
                if (!empty($dimensions['width'])) { $product_node->addChild('width_cm', esc_attr($dimensions['width'])); }
                if (!empty($dimensions['height'])) { $product_node->addChild('height_cm', esc_attr($dimensions['height'])); }
                $shipping_class_id = $product->get_shipping_class_id();
                if ($shipping_class_id) {
                     $product_node->addChild('shipping_class_id', $shipping_class_id);
                     $shipping_class_term = get_term($shipping_class_id, 'product_shipping_class');
                     if ($shipping_class_term && !is_wp_error($shipping_class_term)) {
                         $product_node->addChild('shipping_class_name')->addCData( $shipping_class_term->name );
                     }
                }

                // --- Images ---
                $main_image_url = wp_get_attachment_url($product->get_image_id());
                if ($main_image_url) {
                    $product_node->addChild('main_image_url', esc_url($main_image_url));
                }
                $gallery_ids = $product->get_gallery_image_ids();
                if (!empty($gallery_ids)) {
                    $gallery_node = $product_node->addChild('gallery_images');
                    foreach ($gallery_ids as $gallery_id) {
                        $gallery_url = wp_get_attachment_url($gallery_id);
                        if ($gallery_url) {
                            $gallery_node->addChild('gallery_image_url', esc_url($gallery_url));
                        }
                    }
                }

                // --- Categories and Tags ---
                // Category Path (Example: Cat A > SubCat B) - Already implemented, check format needed by BuyAdwiser
                $category_ids = $product->get_category_ids();
                $category_path = '';
                if (!empty($category_ids)) {
                    $term_id = $category_ids[0]; // Use the first category
                    $term = get_term($term_id, 'product_cat');
                    if ($term && !is_wp_error($term)) {
                        $parents = get_ancestors($term->term_id, 'product_cat', 'taxonomy');
                        $parents = array_reverse($parents);
                        $path_parts = [];
                        foreach ($parents as $parent_id) {
                            $parent_term = get_term($parent_id, 'product_cat');
                            if ($parent_term && !is_wp_error($parent_term)) {
                                $path_parts[] = $parent_term->name;
                            }
                        }
                        $path_parts[] = $term->name;
                        $category_path = implode(' > ', $path_parts); // !! Adjust separator ' > ' if needed
                    }
                }
                $product_node->addChild('category_path')->addCData( $category_path );

                // Tags
                $tag_names = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
                if (!empty($tag_names)) {
                     $tags_node = $product_node->addChild('tags');
                     foreach ($tag_names as $tag_name) {
                         $tags_node->addChild('tag')->addCData( $tag_name );
                     }
                }

                // Brands
                $brand_names = wp_get_post_terms($product->get_id(), 'product_brand', array('fields' => 'names'));
                if (!empty($brand_names)) {
                     $brands_node = $product_node->addChild('brand_tags');
                     foreach ($brand_names as $brand_name) {
                        $brands_node->addChild('brand')->addCData( $brand_name );
                     }
                }

                // --- Attributes (Including potential Brand/EAN) ---
                // !! Adapt the structure (<attribute name="...">) if BuyAdwiser requires different format !!
                $attributes = $product->get_attributes();
                if (!empty($attributes)) {
                    $attributes_node = $product_node->addChild('attributes');
                    foreach ($attributes as $attribute) {
                        if ( $attribute->get_visible() || !$attribute->is_taxonomy() ) { // Get visible attributes or non-taxonomy ones
                            $attr_node = $attributes_node->addChild('attribute');
                            $attr_name = $attribute->get_name();
                            // If it's a taxonomy attribute (like pa_color), get the proper name
                            if ($attribute->is_taxonomy()) {
                                $taxonomy_name = $attribute->get_name(); // e.g., 'pa_color'
                                $taxonomy_obj = get_taxonomy($taxonomy_name);
                                if ($taxonomy_obj) {
                                    $attr_name = $taxonomy_obj->labels->singular_name; // e.g., 'Color'
                                }
                                $attr_node->addAttribute('taxonomy', $taxonomy_name); // Add slug as attribute
                            }
                            $attr_node->addAttribute('name', $attr_name); // Add readable name as attribute

                            // Get the value(s)
                            $attr_values = $product->get_attribute($attribute->get_name());
                            $attr_node->addChild('value')->addCData( $attr_values );

                            // Store Brand/EAN separately if found (adapt slugs 'pa_brand', 'pa_ean')
                            if ( $attribute->get_name() === 'pa_brand' ) { // CHANGE 'pa_brand' if needed
                                 $product_node->addChild('brand')->addCData($attr_values);
                            }
                            if ( $attribute->get_name() === 'pa_ean' ) { // CHANGE 'pa_ean' if needed
                                 $product_node->addChild('ean', esc_attr($attr_values));
                            }
                        }
                    }
                }
                 // Fallback for Brand/EAN if not found as attributes (e.g., custom fields)
                 if (!$product_node->brand) {
                    $brand_meta = get_post_meta($product->get_id(), '_brand', true); // CHANGE '_brand' if needed
                    if ($brand_meta) $product_node->addChild('brand')->addCData($brand_meta);
                 }
                 if (!$product_node->ean) {
                    $ean_meta = get_post_meta($product->get_id(), '_ean', true); // CHANGE '_ean' if needed
                    if ($ean_meta) $product_node->addChild('ean', esc_attr($ean_meta));
                 }


                // === END XML NODE FOR ONE PRODUCT ===
            }
            wp_reset_postdata(); // Restore global post data
        }

        // 4. Output the XML
        // Use DomDocument for pretty printing if needed, otherwise direct output is fine
        // $dom = dom_import_simplexml($xml)->ownerDocument;
        // $dom->formatOutput = true;
        // echo $dom->saveXML();
        echo $xml->asXML();

        exit; // Crucial to stop WordPress from outputting anything else
    }
}


/**
 * Helper class to ensure addCData method is available for SimpleXMLElement.
 */
if (!class_exists('SimpleXMLElementExtendedBuyAdwiser')) {
    class SimpleXMLElementExtendedBuyAdwiser extends SimpleXMLElement {
        /**
         * Adds CData text in a target node
         * @param string $cdata_text The CData value to add
         */
        public function addCData(string $cdata_text): void {
            $node = dom_import_simplexml($this);
            if ($node) {
                $no = $node->ownerDocument;
                if ($no) {
                     $node->appendChild($no->createCDATASection($cdata_text));
                }
            }
        }
    }
}
