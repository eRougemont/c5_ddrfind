<?php
namespace Concrete\Package\Finder;
use Concrete\Core\Controller\Controller as RouteController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use User;
use Page;


class Info extends RouteController
{
  public function echo() {
    $start_time = microtime(true); 
    $uinfo = new User();
    // do not allow admin op by anonymous
    if(!$uinfo->isLoggedIn()) return Response::create('
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 54 54">
  <path d="M13,36H9V1c0-0.553-0.448-1-1-1S7,0.447,7,1v35H3c-0.552,0-1,0.447-1,1v12c0,0.553,0.448,1,1,1h4v3c0,0.553,0.448,1,1,1
    s1-0.447,1-1v-3h4c0.552,0,1-0.447,1-1V37C14,36.447,13.552,36,13,36z M12,48H4V38h8V48z"/>
  <path d="M32,20h-4V1c0-0.553-0.448-1-1-1s-1,0.447-1,1v19h-4c-0.552,0-1,0.447-1,1v12c0,0.553,0.448,1,1,1h4v19
    c0,0.553,0.448,1,1,1s1-0.447,1-1V34h4c0.552,0,1-0.447,1-1V21C33,20.447,32.552,20,32,20z M31,32h-8V22h8V32z"/>
  <path d="M51,4h-4V1c0-0.553-0.448-1-1-1s-1,0.447-1,1v3h-4c-0.552,0-1,0.447-1,1v12c0,0.553,0.448,1,1,1h4v35c0,0.553,0.448,1,1,1
    s1-0.447,1-1V18h4c0.552,0,1-0.447,1-1V5C52,4.447,51.552,4,51,4z M50,16h-8V6h8V16z"/>
</svg>
    
    ', 200, array('Content-Type'=>'image/svg+xml'))
      ->setCharset("UTF-8")
    ;
    
    phpinfo();
  }
}
?>
