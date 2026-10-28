<?php
/**
 * Template della pagina di login autonoma (modalità "Standalone page").
 *
 * Documento HTML minimale e indipendente dal tema: non usa get_header()/get_footer()
 * né wp_head(), quindi non carica gli script e gli stili del tema.
 * Per personalizzarlo copia questo file in `{tuo-tema}/wp-simple-access-control/login-page.php`.
 *
 * Variabili disponibili in `$args`:
 * - form      string  HTML del form (già escapato, prodotto da login-form.php).
 * - title     string  Testo del tag <title>.
 * - site_name string  Nome del sito.
 * - home_url  string  URL della home.
 *
 * @package WPSimpleAccessControl
 *
 * @var array<string,mixed> $args
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $args['title'] ); ?></title>
	<?php wp_print_styles( 'wpsac-frontend' ); ?>
</head>
<body class="wpsac-login-page">
	<main class="wpsac-login-page__main">
		<h1 class="wpsac-login-page__title">
			<a class="wpsac-login-page__home" href="<?php echo esc_url( $args['home_url'] ); ?>"><?php echo esc_html( $args['site_name'] ); ?></a>
		</h1>
		<div class="wpsac-login-page__card">
			<?php echo $args['form']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già escapato da login-form.php. ?>
		</div>
	</main>
</body>
</html>
