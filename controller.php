<?php
namespace Concrete\Package\Finder;
use Concrete\Core\Package\Package;
use Route;


class Controller extends Package
{

  protected $pkgHandle = 'finder';
  protected $appVersionRequired = '8.0';
  protected $pkgVersion = '0.0.1';
  protected $pkgAutoloaderRegistries = array(
    'src/' => 'Concrete\Package\Finder',
  );

  public function getPackageDescription()
  {
    return t('Trouver des pages');
  }

  public function getPackageName()
  {
    return t('Trouveur');
  }

  public function install()
  {
    $pkg = parent::install();
  }
  
  public function on_start()
  {
    Route::register('/data/titles', '\Concrete\Package\Finder\Titles::echo');
    Route::register('/data/info', '\Concrete\Package\Finder\Info::echo');
    // Au cas où, pour mémoire
    // $al = AssetList::getInstance();
    // $al->register('css', 'hw_testimonials', 'css/hw_testimonials.css', array('version' => '1', 'position' => Asset::ASSET_POSITION_HEADER, 'minify' => false, 'combine' => false), $this);
  }

  public function upgrade()
  {
  }

}
