<?php
/**
 * Class WC_BuyAdwiser_Feed_Generator
 *
 * Handles the XML feed generation
 *
 * @package WC_BuyAdwiser_Feed
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_BuyAdwiser_Feed_Generator {

    /**
     * Plugin options
     *
     * @var array
     */
    protected $options = [];
    
    /**
     * Constructor
     *
     * @param array $options Plugin options
     */
    public function __construct( $options ) {
        $this->options = $options;
    }
    
    /**
     * Generate XML feed
     *
     * @return string Generated XML
     */
    public function generate() {
        // Create extended SimpleXML object with CDATA support
        $xml = new SimpleXMLElementExtended('<?xml version="1.0" encoding="UTF-8"?><products></products>');
        
        // Get products
        $products = $this->get_products();
        
        if ( ! empty( $products ) ) {
            foreach ( $products as $product ) {
                // Skip invalid products
                if ( ! is_object( $product ) || ! $product instanceof WC_Product ) {
                    continue;
                }
                
                // Add product to XML
                $this->add_product_to_xml( $xml, $product );
            }
        }
        
        // Format output with DOMDocument for better readability
        $dom = dom_import_simplexml( $xml )->ownerDocument;
        $dom->formatOutput = true;
        
        return $dom->saveXML();
    }
    
    /**
     * Get products for the feed
     *
     * @return array
     */
    protected function get_products() {
        // Base query arguments
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_visibility',
                    'field'    => 'name',
                    'terms'    => 'exclude-from-catalog',
                    'operator' => 'NOT IN',
                ),
            ),
        );
        
        // Apply result limit if enabled
        if ( isset( $this->options['limit_results'] ) && $this->options['limit_results'] === 'yes' ) {
            $limit = isset( $this->options['results_limit'] ) ? absint( $this->options['results_limit'] ) : 1000;
            $args['posts_per_page'] = $limit;
        } else {
            $args['posts_per_page'] = -1; // No limit
        }
        
        // Sort by newest first
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
        
        // Run the query
        $query = new WP_Query( $args );
        
        $products = array();
        $variations_format = isset( $this->options['variations_format'] ) ? $this->options['variations_format'] : 'separate';
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $product = wc_get_product( get_the_ID() );
                
                if ( $product ) {
                    // Skip products without price (unless variable type)
                    if ( ! $product->get_price() && ! $product->is_type( 'variable' ) ) {
                        continue;
                    }
                    
                    // Always add the product (whether it's simple, variable, etc.)
                    $products[] = $product;
                    
                    // Only add variations as separate entries if using 'separate' format
                    if ( $product->is_type( 'variable' ) && $variations_format === 'separate' ) {
                        $variations = $product->get_available_variations();
                        
                        foreach ( $variations as $variation ) {
                            $variation_product = wc_get_product( $variation['variation_id'] );
                            
                            if ( $variation_product && $variation_product->get_price() ) {
                                $products[] = $variation_product;
                            }
                        }
                    }
                }
            }
            
            wp_reset_postdata();
        }
        
        return $products;
    }
    
    /**
     * Add product to XML
     *
     * @param SimpleXMLElementExtended $xml
     * @param WC_Product $product
     */
    protected function add_product_to_xml( $xml, $product ) {
        // Create product node
        $product_node = $xml->addChild( 'product' );
        
        // === Core Identification ===
        $product_node->addChild( 'internal_id', $product->get_id() );
        
        $sku = $product->get_sku();
        if ( ! empty( $sku ) ) {
            $product_node->addChild( 'sku', esc_attr( $sku ) );
        }
        
        $product_node->addChild( 'product_type', esc_attr( $product->get_type() ) );
        
        // === Basic Information ===
        $product_node->addChild( 'name' )->addCData( $product->get_name() );
        $product_node->addChild( 'url', esc_url( get_permalink( $product->get_id() ) ) );
        
        // Description
        $description = $product->get_description();
        if ( ! empty( $description ) ) {
            $product_node->addChild( 'description' )->addCData( wp_strip_all_tags( $description ) );
        }
        
        // Short description
        $short_desc = $product->get_short_description();
        if ( ! empty( $short_desc ) ) {
            $product_node->addChild( 'short_description' )->addCData( wp_strip_all_tags( $short_desc ) );
        }
        
        // === Pricing ===
        $this->add_pricing_data( $product_node, $product );
        
        // === Inventory ===
        $this->add_inventory_data( $product_node, $product );
        
        // === Shipping ===
        $this->add_shipping_data( $product_node, $product );
        
        // === Images ===
        $this->add_image_data( $product_node, $product );
        
        // === Categories, Tags, and Brands ===
        $this->add_taxonomy_data( $product_node, $product );
        
        // === Attributes and Custom Fields ===
        $this->add_attribute_data( $product_node, $product );
        
        // === Handle Variable Products with Nested Format ===
        $variations_format = isset( $this->options['variations_format'] ) ? $this->options['variations_format'] : 'separate';
        
        if ( $product->is_type( 'variable' ) && $variations_format === 'nested' ) {
            $this->add_variations_to_product( $product_node, $product );
        }
    }
    
    /**
     * Add variations as nested elements to a variable product
     *
     * @param SimpleXMLElementExtended $product_node
     * @param WC_Product_Variable $product
     */
    protected function add_variations_to_product( $product_node, $product ) {
        $variations = $product->get_available_variations();
        
        if ( ! empty( $variations ) ) {
            $variations_node = $product_node->addChild( 'variations' );
            
            foreach ( $variations as $variation_data ) {
                $variation_id = $variation_data['variation_id'];
                $variation_product = wc_get_product( $variation_id );
                
                if ( $variation_product && $variation_product->get_price() ) {
                    $variation_node = $variations_node->addChild( 'variation' );
                    
                    // Add core identification
                    $variation_node->addChild( 'internal_id', $variation_product->get_id() );
                    
                    $sku = $variation_product->get_sku();
                    if ( ! empty( $sku ) ) {
                        $variation_node->addChild( 'sku', esc_attr( $sku ) );
                    }
                    
                    // Add name and URL
                    $variation_node->addChild( 'name' )->addCData( $variation_product->get_name() );
                    $variation_node->addChild( 'url', esc_url( $variation_product->get_permalink() ) );
                    
                    // Add pricing
                    $this->add_pricing_data( $variation_node, $variation_product );
                    
                    // Add inventory
                    $this->add_inventory_data( $variation_node, $variation_product );
                    
                    // Add shipping if available
                    if ( $variation_product->get_weight() || $variation_product->get_dimensions( false ) ) {
                        $this->add_shipping_data( $variation_node, $variation_product );
                    }
                    
                    // Add image if different from parent
                    if ( $variation_product->get_image_id() ) {
                        $this->add_image_data( $variation_node, $variation_product );
                    }
                    
                    // Add attributes
                    $this->add_attribute_data( $variation_node, $variation_product );
                }
            }
        }
    }
    
    /**
     * Add pricing data to product node
     *
     * @param SimpleXMLElementExtended $product_node
     * @param WC_Product $product
     */
    protected function add_pricing_data( $product_node, $product ) {
        // Handle variable products differently
        if ( $product->is_type( 'variable' ) ) {
            // Try to get the default variation 
            $default_attributes = $product->get_default_attributes();
            $default_variation_id = null;
            $default_variation = null;
            
            // If default attributes are set, find the matching variation
            if ( ! empty( $default_attributes ) ) {
                $data_store = WC_Data_Store::load( 'product' );
                $default_variation_id = $data_store->find_matching_product_variation( $product, $default_attributes );
                
                if ( $default_variation_id ) {
                    $default_variation = wc_get_product( $default_variation_id );
                }
            }
            
            // If we have a default variation, use its pricing
            if ( $default_variation ) {
                // Regular price
                $regular_price = $default_variation->get_regular_price();
                if ( ! empty( $regular_price ) ) {
                    $product_node->addChild( 'regular_price', esc_attr( wc_format_decimal( $regular_price, 2 ) ) );
                }
                
                // Sale price
                $sale_price = $default_variation->get_sale_price();
                if ( ! empty( $sale_price ) ) {
                    $product_node->addChild( 'sale_price', esc_attr( wc_format_decimal( $sale_price, 2 ) ) );
                }
                
                // On sale status
                $product_node->addChild( 'on_sale', $default_variation->is_on_sale() ? 'true' : 'false' );
                
                // Display price for the default variation
                $display_price = wc_get_price_to_display( $default_variation );
                $product_node->addChild( 'price', esc_attr( wc_format_decimal( $display_price, 2 ) ) );
            } else {
                // Fall back to WooCommerce's default price display logic
                $price_display = get_option( 'woocommerce_product_price_display', 'min' );
                
                // Default to 'min' if not set
                if ( empty( $price_display ) ) {
                    $price_display = 'min';
                }
                
                // Get prices based on store settings
                $regular_price = $product->get_variation_regular_price( $price_display );
                $sale_price = $product->get_variation_sale_price( $price_display );
                
                // Add regular price
                if ( ! empty( $regular_price ) ) {
                    $product_node->addChild( 'regular_price', esc_attr( wc_format_decimal( $regular_price, 2 ) ) );
                }
                
                // Add sale price
                if ( ! empty( $sale_price ) && $sale_price < $regular_price ) {
                    $product_node->addChild( 'sale_price', esc_attr( wc_format_decimal( $sale_price, 2 ) ) );
                }
                
                // Set on_sale flag
                $product_node->addChild( 'on_sale', $product->is_on_sale() ? 'true' : 'false' );
                
                // Display price (already includes tax settings)
                $display_price = wc_get_price_to_display( $product );
                $product_node->addChild( 'price', esc_attr( wc_format_decimal( $display_price, 2 ) ) );
            }
        } else {
            // Regular products (simple, variations, etc.)
            
            // Regular price
            $regular_price = $product->get_regular_price();
            if ( ! empty( $regular_price ) ) {
                $product_node->addChild( 'regular_price', esc_attr( wc_format_decimal( $regular_price, 2 ) ) );
            }
            
            // Sale price
            $sale_price = $product->get_sale_price();
            if ( ! empty( $sale_price ) ) {
                $product_node->addChild( 'sale_price', esc_attr( wc_format_decimal( $sale_price, 2 ) ) );
            }
            
            // On sale status
            $product_node->addChild( 'on_sale', $product->is_on_sale() ? 'true' : 'false' );
            
            // Current price including tax if applicable
            $display_price = wc_get_price_to_display( $product );
            $product_node->addChild( 'price', esc_attr( wc_format_decimal( $display_price, 2 ) ) );
        }
        
        // Sale dates - only for non-variable products
        if ( ! $product->is_type( 'variable' ) ) {
            $sale_start = $product->get_date_on_sale_from();
            $sale_end = $product->get_date_on_sale_to();
            
            if ( $sale_start ) {
                $product_node->addChild( 'sale_price_effective_date_start', $sale_start->date( 'Y-m-d' ) );
            }
            
            if ( $sale_end ) {
                $product_node->addChild( 'sale_price_effective_date_end', $sale_end->date( 'Y-m-d' ) );
            }
        }
        
        // Tax information
        $product_node->addChild( 'tax_status', esc_attr( $product->get_tax_status() ) );
        $product_node->addChild( 'tax_class', esc_attr( $product->get_tax_class() ) );
    }
    
    /**
     * Add inventory data to product node
     *
     * @param SimpleXMLElementExtended $product_node
     * @param WC_Product $product
     */
    protected function add_inventory_data( $product_node, $product ) {
        $product_node->addChild( 'stock_status', esc_attr( $product->get_stock_status() ) );
        $product_node->addChild( 'manage_stock', $product->get_manage_stock() ? 'true' : 'false' );
        
        if ( $product->get_manage_stock() ) {
            $product_node->addChild( 'stock_quantity', intval( $product->get_stock_quantity() ) );
        }
        
        $product_node->addChild( 'backorders_allowed', $product->backorders_allowed() ? 'true' : 'false' );
        $product_node->addChild( 'sold_individually', $product->is_sold_individually() ? 'true' : 'false' );
    }
    
    /**
     * Add shipping data to product node
     *
     * @param SimpleXMLElementExtended $product_node
     * @param WC_Product $product
     */
    protected function add_shipping_data( $product_node, $product ) {
        // Weight
        $weight = $product->get_weight();
        if ( ! empty( $weight ) ) {
            $product_node->addChild( 'weight_kg', esc_attr( $weight ) );
        }
        
        // Dimensions
        $dimensions = $product->get_dimensions( false );
        
        if ( ! empty( $dimensions['length'] ) ) {
            $product_node->addChild( 'length_cm', esc_attr( $dimensions['length'] ) );
        }
        
        if ( ! empty( $dimensions['width'] ) ) {
            $product_node->addChild( 'width_cm', esc_attr( $dimensions['width'] ) );
        }
        
        if ( ! empty( $dimensions['height'] ) ) {
            $product_node->addChild( 'height_cm', esc_attr( $dimensions['height'] ) );
        }
        
        // Shipping class
        $shipping_class_id = $product->get_shipping_class_id();
        if ( $shipping_class_id ) {
            $product_node->addChild( 'shipping_class_id', $shipping_class_id );
            
            $shipping_class_term = get_term( $shipping_class_id, 'product_shipping_class' );
            if ( $shipping_class_term && ! is_wp_error( $shipping_class_term ) ) {
                $product_node->addChild( 'shipping_class_name' )->addCData( $shipping_class_term->name );
            }
        }
    }
    
    /**
     * Add image data to product node
     *
     * @param SimpleXMLElementExtended $product_node
     * @param WC_Product $product
     */
    protected function add_image_data( $product_node, $product ) {
        // Main image
        $main_image_id = $product->get_image_id();
        if ( $main_image_id ) {
            $main_image_url = wp_get_attachment_url( $main_image_id );
            if ( $main_image_url ) {
                $product_node->addChild( 'main_image_url', esc_url( $main_image_url ) );
            }
        }
        
        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        if ( ! empty( $gallery_ids ) ) {
            $gallery_node = $product_node->addChild( 'gallery_images' );
            
            foreach ( $gallery_ids as $gallery_id ) {
                $gallery_url = wp_get_attachment_url( $gallery_id );
                if ( $gallery_url ) {
                    $gallery_node->addChild( 'gallery_image_url', esc_url( $gallery_url ) );
                }
            }
        }
    }
    
    /**
     * Add taxonomy data (categories, tags, brands) to product node
     *
     * @param SimpleXMLElementExtended $product_node
     * @param WC_Product $product
     */
    protected function add_taxonomy_data( $product_node, $product ) {
        // Categories
        $category_ids = $product->get_category_ids();
        $category_path = '';
        
        if ( ! empty( $category_ids ) ) {
            $term_id = $category_ids[0]; // Use the first category
            $term = get_term( $term_id, 'product_cat' );
            
            if ( $term && ! is_wp_error( $term ) ) {
                $parents = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );
                $parents = array_reverse( $parents );
                $path_parts = array();
                
                foreach ( $parents as $parent_id ) {
                    $parent_term = get_term( $parent_id, 'product_cat' );
                    if ( $parent_term && ! is_wp_error( $parent_term ) ) {
                        $path_parts[] = $parent_term->name;
                    }
                }
                
                $path_parts[] = $term->name;
                $category_path = implode( ' > ', $path_parts );
            }
        }
        
        $product_node->addChild( 'category_path' )->addCData( $category_path );
        
        // Tags
        $tag_names = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );
        if ( ! empty( $tag_names ) ) {
            $tags_node = $product_node->addChild( 'tags' );
            
            foreach ( $tag_names as $tag_name ) {
                $tags_node->addChild( 'tag' )->addCData( $tag_name );
            }
        }
        
        // Brands - first check if product_brand taxonomy exists
        if ( taxonomy_exists( 'product_brand' ) ) {
            $brand_names = wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'names' ) );
            
            if ( ! empty( $brand_names ) ) {
                $brands_node = $product_node->addChild( 'brand_tags' );
                
                foreach ( $brand_names as $brand_name ) {
                    $brands_node->addChild( 'brand' )->addCData( $brand_name );
                }
                
                // Also add the first brand as the main brand
                $product_node->addChild( 'brand' )->addCData( $brand_names[0] );
            }
        }
    }
    
    /**
     * Add attribute and custom field data to product node
     *
     * @param SimpleXMLElementExtended $product_node
     * @param WC_Product $product
     */
    protected function add_attribute_data( $product_node, $product ) {
        // Product attributes
        $attributes = $product->get_attributes();
        
        if ( ! empty( $attributes ) ) {
            $attributes_node = $product_node->addChild( 'attributes' );
            
            foreach ( $attributes as $attribute_name => $attribute ) {
                // Check if attribute is an object or a string
                if ( is_object( $attribute ) ) {
                    // Handle object-type attribute (WC_Product_Attribute)
                    if ( $attribute->get_visible() || ! $attribute->is_taxonomy() ) {
                        $attr_node = $attributes_node->addChild( 'attribute' );
                        $attr_name = $attribute->get_name();
                        
                        // Get proper name for taxonomy attributes
                        if ( $attribute->is_taxonomy() ) {
                            $taxonomy_name = $attribute->get_name(); // e.g., 'pa_color'
                            $taxonomy_obj = get_taxonomy( $taxonomy_name );
                            
                            if ( $taxonomy_obj ) {
                                $attr_name = $taxonomy_obj->labels->singular_name; // e.g., 'Color'
                            }
                            
                            $attr_node->addAttribute( 'taxonomy', $taxonomy_name );
                        }
                        
                        $attr_node->addAttribute( 'name', $attr_name );
                        
                        // Get attribute values
                        $attr_values = $product->get_attribute( $attribute->get_name() );
                        $attr_node->addChild( 'value' )->addCData( $attr_values );
                        
                        // Check for special attributes like brand or EAN
                        if ( $attribute->get_name() === 'pa_brand' && ! isset( $product_node->brand ) ) {
                            $product_node->addChild( 'brand' )->addCData( $attr_values );
                        }
                        
                        if ( $attribute->get_name() === 'pa_ean' && ! isset( $product_node->ean ) ) {
                            $product_node->addChild( 'ean', esc_attr( $attr_values ) );
                        }
                    }
                } else {
                    // Handle string-type attribute (older WooCommerce versions or variation attributes)
                    $attr_node = $attributes_node->addChild( 'attribute' );
                    
                    // Try to get taxonomy info if it's a taxonomy attribute
                    if ( taxonomy_exists( $attribute_name ) ) {
                        $taxonomy_obj = get_taxonomy( $attribute_name );
                        $attr_name = $taxonomy_obj ? $taxonomy_obj->labels->singular_name : $attribute_name;
                        $attr_node->addAttribute( 'taxonomy', $attribute_name );
                    } else {
                        $attr_name = $attribute_name;
                    }
                    
                    $attr_node->addAttribute( 'name', $attr_name );
                    
                    // Get attribute value
                    $attr_values = $product->get_attribute( $attribute_name );
                    $attr_node->addChild( 'value' )->addCData( $attr_values );
                    
                    // Check for special attributes
                    if ( $attribute_name === 'pa_brand' && ! isset( $product_node->brand ) ) {
                        $product_node->addChild( 'brand' )->addCData( $attr_values );
                    }
                    
                    if ( $attribute_name === 'pa_ean' && ! isset( $product_node->ean ) ) {
                        $product_node->addChild( 'ean', esc_attr( $attr_values ) );
                    }
                }
            }
        }
        
        // Check for brand/EAN in custom fields if not found in attributes
        if ( ! isset( $product_node->brand ) ) {
            $brand_meta = get_post_meta( $product->get_id(), '_brand', true );
            if ( $brand_meta ) {
                $product_node->addChild( 'brand' )->addCData( $brand_meta );
            }
        }
        
        if ( ! isset( $product_node->ean ) ) {
            $ean_meta = get_post_meta( $product->get_id(), '_ean', true );
            if ( $ean_meta ) {
                $product_node->addChild( 'ean', esc_attr( $ean_meta ) );
            }
        }
    }
}

/**
 * Extended SimpleXMLElement with CDATA support
 */
class SimpleXMLElementExtended extends SimpleXMLElement {
    
    /**
     * Add CDATA text to a node
     *
     * @param string $cdata_text The text to be enclosed in CDATA tags
     */
    public function addCData( $cdata_text ) {
        $node = dom_import_simplexml( $this );
        if ( $node ) {
            $no = $node->ownerDocument;
            if ( $no ) {
                $node->appendChild( $no->createCDATASection( $cdata_text ) );
            }
        }
    }
}