<?php
namespace LubusIN\ComposerEddPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\Package\PackageInterface;
use Dotenv\Dotenv;
use Exception;
use LubusIN\ComposerEddPlugin\Exception\MissingAuthException;
use LubusIN\ComposerEddPlugin\Exception\MissingExtraException;
use LubusIN\ComposerEddPlugin\Exception\MissingEnvException;

/**
 * Custom Installer Plugin Class.
 */
class Plugin implements PluginInterface, EventSubscriberInterface {

	protected $composer;
	protected $io;
	protected $downloadUrl;

	/**
	 * Activate plugin.
	 *
	 * @param Composer    $composer
	 * @param IOInterface $io
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
		$this->io       = $io;

		if ( file_exists( getcwd() . DIRECTORY_SEPARATOR . '.env' ) ) {
			$dotenv = Dotenv::createUnsafeImmutable( getcwd() );
			$dotenv->load();
		}
	}

	public function uninstall(Composer $composer, IOInterface $io){

	}

	public function deactivate(Composer $composer, IOInterface $io){
	}

	/**
	 * Set subscribed events.
	 *
	 * @return array
	 */
	public static function getSubscribedEvents() {
		return array(
			//PackageEvents::PRE_PACKAGE_INSTALL => 'getDownloadUrl',
			//PackageEvents::PRE_PACKAGE_UPDATE => 'getDownloadUrl',
			PluginEvents::PRE_FILE_DOWNLOAD => 'onPreFileDownload',
		);
	}

	/**
	 * Get package from operation.
	 *
	 * @param OperationInterface $operation
	 * @return mixed
	 */
	protected function getPackageFromOperation( OperationInterface $operation ) {
		if ( 'update' === $operation->getOperationType() ) {
			return $operation->getTargetPackage();
		}
		return $operation->getPackage();
	}

	/**
	 * Get download URL for our plugins.
	 *
	 * @param PackageEvent $event
	 */
	public function getDownloadUrl( $package) {
		$downloadUrl = '';

		$package_version = $package->getPrettyVersion();
        $package_dist_url  = $package->getDistUrl();
		$package_extra     = $package->getExtra();

		if (!empty( $package_extra['edd_installer'])) {
			if (empty($package_extra['item_name'])) {
				throw new MissingExtraException('item_name');
			}

			if (empty($package_extra['license'])) {
				throw new MissingExtraException('license');
			}
			else {
				if (!getenv($package_extra['license'])) {
					throw new MissingEnvException('license');
				}
			}

			if (empty($package_extra['url'])) {
				throw new MissingExtraException('url');
			} else {
				if (!getenv($package_extra['url'])) {
					throw new MissingEnvException('url');
				}
			}

			$package_details = [
				'edd_action' => 'get_version',
				'license'    => getenv( $package_extra['license'] ),

				'url'        => getenv( $package_extra['url'] ),
				'version'    => $package_version,
			];
			//var_dump(is_string($package_extra['item_name']),$package_extra['item_name']);
			$package_details[is_string($package_extra['item_name']) ? 'item_name' : 'item_id'] = $package_extra['item_name'];
			$url = $package_dist_url . '?' . http_build_query($package_details);

			$content = [
                "action"=>"awp_get_addon_download_link",
                "addonId"=>345,
                "paymentData"=> [
                    "user_email"=>"luca@mosalingua.com",
                    "payment_id"=>146746,
                ]
            ];
            $query =  http_build_query($package_details);
            $context = stream_context_create([
				"http" => [
					"method"  => "POST",
					'header'  =>[
						"Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
						'Content-Length: '.strlen($query),
					],
                    'content' => $query,
					"timeout" => 30,
				],
			]);

			$edd_response = file_get_contents("https://affiliatewp.com/wp-admin/admin-ajax.php", false, $context);

			if( !$edd_response) {
				throw new Exception('Unable to connect to ' . $package_dist_url);
			}

			//$edd_data = json_decode($edd_response, true);
			//if( !empty($edd_data['download_link'])) {
			//}
            $downloadUrl = $edd_response;

		}
		return $downloadUrl;
	}

	/**
	 * Process our plugin downloads.
	 *
	 * @param PreFileDownloadEvent $event
	 */
	public function onPreFileDownload( PreFileDownloadEvent $event ) {
			$package = $event->getContext();
		if ( $event->getType() === 'package' && $package instanceof PackageInterface) {
			$downloadUrl = $this->getDownloadUrl($package);
		}

		if ( empty( $downloadUrl ) ) {
			return;
		}

		$event->setProcessedUrl($downloadUrl);
		$event->setCustomCacheKey($downloadUrl);
	}

}
