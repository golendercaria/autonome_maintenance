<?php
/**
 * Plugin name: Autonome Maintenance
 * Plugin URI: https://github.com/golendercaria/autonome_maintenance
 * Author: Vangampelaere Yann
 * Version: 0.1
 * Description: Set maintenance without dependence of WP
 * Tested up to: 6.0.0
 * Text Domain: autonome-maintenance
 * License: None
 * License URI: None
 * Domain Path: /languages
 */

namespace GOL;

class AutonomeMaintenance {

	function __construct() {

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		add_action('admin_init', array( $this, 'autonome_maintenance_save_ip_list' ) );

	}

	function add_admin_menu() {
		add_management_page( 'Autonome Maintenance', 'Autonome Maintenance', 'edit_posts', 'autonome-maintenance', array( $this, 'autonome_maintenance_admin_page' ) );
	}

	function check_maintenance_directory(){
		return file_exists( ABSPATH . '/maintenance');
	}

	// Fonction pour afficher le formulaire d'administration
	function autonome_maintenance_admin_page() {

		if (current_user_can('manage_options')) {
			// Obtenez l'adresse IP de l'utilisateur actuel
			$user_ip = $_SERVER['REMOTE_ADDR'];

			$maintenance_is_enable = get_option('autonome_maintenance__maintenance_is_enable');

			$maintenance_directory_exists = $this->check_maintenance_directory();

			?>
			<div class="wrap">
				<h2>Autonome Maintenance</h2>

				<?php
					if( $maintenance_directory_exists ){
						?>
					
							<form method="post" action="">
								<h3>Adresse IP actuelle :</h3>
								<input type="input" name="current_ip" id="current_ip" value="<?php echo $user_ip; ?>" />
								<input type="submit" name="add_current_ip" id="add_current_ip" value="Ajouter mon IP à la liste" class="button-primary" />

								<h3>Liste d'adresses IP non sujet à la redirection :</h3>
								<?php
									//str_replace(" ", "-", get_option('autonome_maintenance_ip_list'));
								?>
								<textarea id="ip_list" name="ip_list" rows="4" cols="50" wrap="soft"><?php echo esc_textarea(str_replace(" ", "\n", get_option('autonome_maintenance_ip_list'))); ?></textarea>

								<br/>
								<input type="checkbox" id="maintenance_is_enable" name="maintenance_is_enable" value="true" <?php if($maintenance_is_enable){ echo 'checked'; } ?> />
								<label for="maintenance_is_enable">maintenance_is_enable</label>
								
								<br/>
								<input type="submit" name="save_ip_list" value="Enregistrer" class="button-primary" />
							</form>
					
						<script type="text/javascript">
							document.addEventListener('DOMContentLoaded', function() {

								var addButton = document.querySelector('#add_current_ip');
								var ipListTextarea = document.querySelector('#ip_list');
								var currentIpListInput = document.querySelector('#ip_list');

								addButton.addEventListener('click', function(e) {

									e.preventDefault();

									let currentIp = "<?php echo $_SERVER['REMOTE_ADDR']; ?>";
									let currentIpList = currentIpListInput.value;

									if (currentIpList.indexOf(currentIp) === -1) {

										if (currentIpList !== '') {
											currentIpList += '\n'; // Ajouter un retour à la ligne s'il y a déjà des adresses IP
										}
										currentIpList += currentIp;
										ipListTextarea.value += '\n' + currentIp; // Ajouter l'adresse IP courante au textarea
										currentIpListInput.value = currentIpList; // Mettre à jour la valeur du champ caché
									}
								});
							});
						</script>
						<?php
					}else{
						?>
						<div class="notice notice-error">
							<p>Veuillez créer un dossier de maintenance à la racine du site (<?php echo ABSPATH; ?>)</p>
						</div>
						<?php
		
					}
				?>
			</div>
			<?php
		}
	}

	// Fonction pour enregistrer les paramètres dans la base de données
	function autonome_maintenance_save_ip_list() {
		
		if (isset($_POST['ip_list'])) {

			$ip_list = sanitize_text_field($_POST['ip_list']);
			$maintenance_is_enable = ($_POST["maintenance_is_enable"] == "true" ) ? true : false;

			update_option('autonome_maintenance_ip_list', $ip_list);
			update_option('autonome_maintenance__maintenance_is_enable', $maintenance_is_enable );

			// Modifier le .htaccess
			$htaccess_path = ABSPATH . '.htaccess';
			if (file_exists($htaccess_path) && is_writable($htaccess_path)) {

				$htaccess_content = file_get_contents($htaccess_path);

				// Supprimer les anciens commentaires Autonome Maintenance s'ils existent
				$htaccess_content = preg_replace('/## BEGIN AUTONOME MAINTENANCE ##.*?## END AUTONOME MAINTENANCE ##/s', '', $htaccess_content);

				// Ajouter le nouveau commentaire et le champ input
				$new_htaccess_content = "## BEGIN AUTONOME MAINTENANCE ##\n";

				if( $maintenance_is_enable === true ){
					$new_htaccess_content .= $this->write_redirect_to_maintenance($ip_list);
				}else{
					$new_htaccess_content .= $this->write_redirect_out_of_maintenance();
				}

				$new_htaccess_content .= "## END AUTONOME MAINTENANCE ##\n";
				$new_htaccess_content .= $htaccess_content;

				file_put_contents($htaccess_path, $new_htaccess_content);
			}
		}
	}


	function write_redirect_to_maintenance( $ip_lists ){
		$rule_content = '<IfModule mod_rewrite.c>' . PHP_EOL;
		$rule_content .= 'RewriteEngine On' . PHP_EOL;

		$ip_lists = explode(" ", $ip_lists);
		if( !empty($ip_lists) ){
			foreach($ip_lists as $ip){
				$rule_content .= 'RewriteCond %{REMOTE_ADDR} !^' . $ip . PHP_EOL;
			}
		}

		$rule_content .= 'RewriteCond %{REQUEST_URI} !^/maintenance/.*$' . PHP_EOL;
		$rule_content .= 'RewriteRule .* ' . site_url() . '/maintenance/ [L]' . PHP_EOL;
		$rule_content .= '</IfModule>' . PHP_EOL;

		return $rule_content;
	}

	function write_redirect_out_of_maintenance(){
		$rule_content = '<IfModule mod_rewrite.c>' . PHP_EOL;
		$rule_content .= 'RewriteEngine On' . PHP_EOL;
		$rule_content .= 'RewriteCond %{REQUEST_URI} ^/maintenance/.*$' . PHP_EOL;
		$rule_content .= 'RewriteRule .* ' . site_url() . '/ [L]' . PHP_EOL;
		$rule_content .= '</IfModule>' . PHP_EOL;

		return $rule_content;
	}

}

add_action( 'plugins_loaded', function() {
	new AutonomeMaintenance();
});



/*


// Fonction pour enregistrer les paramètres dans la base de données


// Ajouter une page d'administration pour le plugin
function autonome_maintenance_add_admin_page() {
    add_menu_page('Autonome Maintenance', 'Autonome Maintenance', 'manage_options', 'autonome-maintenance', 'autonome_maintenance_admin_page');
}

// Enregistrer les actions
add_action('admin_menu', 'autonome_maintenance_add_admin_page');
add_action('admin_init', 'autonome_maintenance_save_text');
*/