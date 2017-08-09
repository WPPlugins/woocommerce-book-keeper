<?php if ( ! defined( 'ABSPATH' ) ) exit;

/******ETAPE 3 : PREPARER L'EXPORT******/

function mw_wae_export_data() {
	global $woocommerce, $post;

	//test de nonce
	if ( 
   ! isset( $_POST['_check_export'] )
    || ! wp_verify_nonce( $_POST['_check_export'], 'check_nonce_export') 
)
{

  print 'Sorry, your nonce did not verify.';
   exit;

 } else  {
	
$separator = sanitize_text_field($_POST['mw_wae_export_separator']);
$ts1 = sanitize_text_field($_POST['mw_wae_export_start_date']);
$ts2 = sanitize_text_field($_POST['mw_wae_export_end_date']);

//Test de la version Woocommerce
if ( version_compare( WOOCOMMERCE_VERSION, "2.2.0" ) >= 0 ) {
	
	//on charge sur Woocommerce 2.2
	$orders = get_posts ( array(
			'posts_per_page' => -1,
			'post_type' => 'shop_order',
			'post_status' => array( 'wc-completed' ),
			'orderby' => 'ID',
			'order' => 'ASC',
			'meta_query' => array(
        array(
            'key' => '_completed_date',
            'value' => array($ts1, $ts2),
            'compare' => 'BETWEEN',
            'type' => 'DATE',
        )
    )
		)
		);
		
	} else {
//Ou sur une version précédente
$orders = get_posts ( array(
			'posts_per_page' => -1,
			'post_type' => 'shop_order',
			'meta_query' => array(
        array(
            'key' => '_completed_date',
            'value' => array($ts1, $ts2),
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        )
    ),
			'post_status' => 'publish',
			'orderby' => 'ID',
			'order' => 'ASC',
			'tax_query' => array(
				array(
				'taxonomy' => 'shop_order_status',
				'field' => 'slug',
				'terms' => array('completed')
				)
				)
		)
		);}
		
		//On va chercher les données nécessaires à l'export
		
		if (!empty($orders)) {
		//On définit tous les comptes génériques
						  $book_code = get_option('mw_wae_book_code_order');
						  $account_cust = get_option('mw_wae_generic_cust_accounting_account');
						  $account_prod = get_option ('mw_wae_generic_prod_accounting_account');
						  $account_fdp = get_option ('mw_wae_generic_fdp_accounting_account');
						  $account_tax = get_option ('mw_wae_generic_tax_accounting_account');	
						  $csv_headers = get_option ('mw_wae_columns_headers');
						  
		foreach ($orders as $order) {
						  $order->piecedate = get_post_meta($order->ID,'_completed_date',true);


						  //On définit tous les comptes détaillés
						  $order->number = $order->ID;
						  $order->custid = get_post_meta($order->ID,'_customer_user',true);
						  $order->lib = remove_accents (strtoupper(get_post_meta($order->ID,'_billing_company',true)) . ' ' . ucfirst(get_post_meta($order->ID,'_billing_last_name',true)) . ' ' . ucfirst(get_post_meta($order->ID,'_billing_first_name',true)));
						  $order->outcome = get_post_meta($order->ID,'_order_total',true);
						  $order->income_tax = get_post_meta($order->ID, '_order_tax',true) + get_post_meta($order->ID, '_order_shipping_tax',true);
						  $order->income_fdpht = get_post_meta($order->ID, '_order_shipping',true);
						  $order->income_prodht = ( ($order->outcome) - ( ($order->income_tax) + ($order->income_fdpht) ) );
						  
						  }
						  
			
			
			$output = fopen("php://output",'w') or die ("Can't open php://output");
			
	$filename = 'mw-wae-export-' . $ts1 . '-' . $ts2 . '.csv';
	
	header( 'Content-type: application/csv' );
	header('Content-Transfer-Encoding: UTF-8');
	header( 'Content-Disposition: attachment; filename='.$filename );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	
	/****Tricky Boy : à améliorer *****/
	if (!empty($csv_headers['journal'])) {
		$csv_journal = $csv_headers['journal'];
	} else {
		$csv_journal = 'Code_Journal';
	}
	
	if (!empty($csv_headers['date'])) {
		$csv_date = $csv_headers['date'];
	} else {
		$csv_date = 'Date_de_piece';
	}
	
	if (!empty($csv_headers['number'])) {
		$csv_number = $csv_headers['number'];
	} else {
		$csv_number = 'Numero_de_piece';
	}
	
	if (!empty($csv_headers['code'])) {
		$csv_code = $csv_headers['code'];
	} else {
		$csv_code = 'Compte_Comptable';
	}
	
	if (!empty($csv_headers['label'])) {
		$csv_label = $csv_headers['label'];
	} else {
		$csv_label = 'Libelle';
	}
	
	if (!empty($csv_headers['outcome'])) {
		$csv_outcome = $csv_headers['outcome'];
	} else {
		$csv_outcome = 'Debit';
	}
	
	if (!empty($csv_headers['income'])) {
		$csv_income = $csv_headers['income'];
	} else {
		$csv_income = 'Credit';
	}
	
	if ($separator == "t") {
		

			fputcsv ($output, array(
							$csv_journal,
							$csv_date,
							$csv_number,
							$csv_code,
							$csv_label,
							$csv_outcome,
							$csv_income
							),
					  "\t");
			} else {
				fputcsv ($output, array(
							$csv_journal,
							$csv_date,
							$csv_number,
							$csv_code,
							$csv_label,
							$csv_outcome,
							$csv_income
							),
					  $separator
							);
				}
			
			foreach($orders as $order) {			
						
						//On met en page l'export
				if ($separator == "t") {
					if ($order->outcome != 0) {
					fputcsv ($output, array(
										$book_code,
										$order->piecedate,
										$order->number,
										$account_cust,
										$order->lib,
										round($order->outcome,2)
										),
					 "\t");
					 }
					if ($order->income_fdpht != 0) {
			fputcsv ($output, array(
										$book_code,
										$order->piecedate,
										$order->number,
										$account_fdp,
										$order->lib,
										"",
										round($order->income_fdpht,2)
										),
					 "\t");
					 }
					if ($order->income_prodht != 0) {					
			fputcsv ($output, array(
										$book_code,
										$order->piecedate,
										$order->number,
										$account_prod,
										$order->lib,
										"",
										round($order->income_prodht,2)
										),
					 "\t");
					 }
					if ($order->income_tax != 0) {
			fputcsv ($output, array(
										$book_code,
										$order->piecedate,
										$order->number,
										$account_tax,
										$order->lib,
										"",
										round($order->income_tax,2)
										),
					 "\t");
					}
			}
				
				else {
					if ($order->outcome != 0) {
			fputcsv ($output, array(
										$book_code,
										$order->piecedate,
										$order->number,
										$account_cust,
										$order->lib,
										round($order->outcome,2)
										),
					 $separator);
					 }
					 if ($order->income_fdpht != 0) {
			fputcsv ($output, array(
										$book_code,
										$order->piecedate,
										$order->number,
										$account_fdp,
										$order->lib,
										"",
										round($order->income_fdpht,2)
										),
					 $separator);
					 }
					 if ($order->income_prodht != 0) {
			fputcsv ($output, array(
										$book_code,
										$order->piecedate,
										$order->number,
										$account_prod,
										$order->lib,
										"",
										round($order->income_prodht,2)
										),
					 $separator);
					 }
					if ($order->income_tax != 0) {
			fputcsv ($output, array(
										$book_code,
										$order->piecedate,
										$order->number,
										$account_tax,
										$order->lib,
										"",
										round($order->income_tax,2)
										),
					 $separator);
					}
			}

			}
			

			fclose($output) or die("Can't close php://output");
			exit;
		}
				else { 
			echo ("Pas de commande sur la p&eacute;riode. R&eacute;essayez avec une autre p&eacute;riode.");       }
	



	
   

   }
      
   }
   

?>