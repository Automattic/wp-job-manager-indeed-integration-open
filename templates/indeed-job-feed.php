<?php
/**
 * Generates the content of the job feed to include Indeed records.
 *
 * This template can be overridden by copying it to yourtheme/job-manager-indeed-integration/indeed-job-feed.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     WP Job Manager - Indeed Integration
 * @category    Template
 * @version     2.1.11
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

header( 'Content-Type: text/xml' );
$xml_document = new DOMDocument( '1.0','utf-8' );
$xml_document->formatOutput = true;

/**
 * Create Root Element
*/
$root = $xml_document->createElement( "source" );
$xml_document->appendChild( $root ); //append root element to document

/**
 * Define Indeed Publisher ID
*/
$publisher = $xml_document->createElement( "publisher" );
$publisher->appendChild( $xml_document->createTextNode( get_option('job_manager_indeed_publisher_id' ) ) );
$root->appendChild( $publisher );

/**
 * Define Site URL
*/
$publisherurl = $xml_document->createElement( "publisherurl" );
$publisherurl->appendChild( $xml_document->createTextNode( get_bloginfo( "url" ) ) );
$root->appendChild( $publisherurl );

/**
 * Define Query Arguments
*/
$limit = get_option( 'job_manager_indeed_feed_limit', '150' );
$args  = array(
	'post_type'      => 'job_listing',
	'posts_per_page' => empty( $limit ) ? '-1' : absint( $limit ),
	'post_status'    => 'publish',
	'orderby'        => 'date',
	'order'          => 'DESC',
	'meta_query'     => array(
		'key'     => '_filled',
		'value'   => '1',
		'compare' => '!='
	)
);
$args = apply_filters( 'job_manager_indeed_feed_args', $args );

/**
 * Run the Query
*/
$query = new WP_Query( $args );

/**
 * Loop through results
*/
while ( $query->have_posts() ) : $query->the_post();

	// Recommended format: http://www.indeed.com/intl/en/xmlinfo.html

	// Start Job Element
	$job_element = $xml_document->createElement( "job" );

	// Job title
	$title = $xml_document->createElement("title");
	$title->appendChild($xml_document->createCDATASection( get_the_title() ) );
	$job_element->appendChild( $title );

	// Company name
	$company_name = get_post_meta( get_the_ID(),'_company_name',true);
	$company = $xml_document->createElement("company");
	$company->appendChild($xml_document->createCDATASection( $company_name ));
	$job_element->appendChild($company);

	// Job date
	$date = $xml_document->createElement("date");
	$date->appendChild($xml_document->createCDATASection( get_the_date() ));
	$job_element->appendChild($date);

	// Job ID
	$rooteferencenumber = $xml_document->createElement("referencenumber");
	$rooteferencenumber->appendChild($xml_document->createCDATASection( get_the_ID() ));
	$job_element->appendChild($rooteferencenumber);

	// Job direct URL
	$url = $xml_document->createElement("url");
	$url->appendChild($xml_document->createCDATASection(get_permalink( get_the_ID() )));
	$job_element->appendChild($url);

	// Job Description
	$description = $xml_document->createElement("description");
	$description->appendChild($xml_document->createCDATASection( ( strip_tags( str_replace( "</p>", "\n\n", get_the_content() ) ) ) ) );
	$job_element->appendChild($description);

	// Job Type
	$description = $xml_document->createElement("jobtype");
	$types = wp_get_post_terms( get_the_ID(), 'job_listing_type', array("fields" => "names") );
	$description->appendChild($xml_document->createCDATASection( implode( ',', $types) ) );
	$job_element->appendChild($description);

	// City
	$city = $xml_document->createElement("city");
	$get_city = explode( ',', get_post_meta( get_the_ID(), 'geolocation_city', true ) );
	$city->appendChild($xml_document->createCDATASection( $get_city[0] ) );
	$job_element->appendChild($city);

	// State
	$state = $xml_document->createElement("state");
	$get_state = explode( ',', get_post_meta( get_the_ID(), 'geolocation_state_short', true ) );
	$state->appendChild($xml_document->createCDATASection( $get_state[0] ) );
	$job_element->appendChild($state);

	// Country
	$country = $xml_document->createElement("country");
	$get_country = substr( get_post_meta( get_the_ID(), 'geolocation_country_short', true ), -2);
	$country->appendChild($xml_document->createCDATASection( strtolower( $get_country ) ));
	$job_element->appendChild($country);

	// Categories
	$category = $xml_document->createElement("category");
	$categories = wp_get_post_terms( get_the_ID(), 'job_listing_category', array( "fields" => "names" ) );
	if ( $categories && ! is_wp_error( $categories ) ) {
		$category->appendChild( $xml_document->createCDATASection( implode( ',', $categories ) ) );
	} else {
		$category->appendChild( $xml_document->createCDATASection( '' ) );
	}
	$job_element->appendChild( $category );

	// End Job Element
	$root->appendChild( $job_element );

endwhile;

echo $xml_document->saveXML();
