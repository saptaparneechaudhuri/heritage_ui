<?php

namespace Drupal\heritage_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;

/**
 * Provides a block to Display Audio Play Options.
 *
 * @Block(
 *   id = "heritage_metadata",
 *   admin_label = @Translation("Display Metadata"),
 *   category = @Translation("Custom")
 * )
 */
class DisplayMetadata extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Path\CurrentPathStack definition.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */

  protected $currPath;

  /**
   * The link generator service.
   *
   * @var pathLink\Drupal\Core\Utility\LinkGeneratorInterface
   */


  protected $pathLink;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentPathStack $currPath, LinkGeneratorInterface $pathLink) {

    parent:: __construct($configuration, $plugin_id, $plugin_definition);
    $this->currPath = $currPath;
    $this->pathLink = $pathLink;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

    return new static(

      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path.current'),
      $container->get('link_generator')
    );
  }

  /**
   *
   */
  public function build() {
    $builtForm = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\Metadata');
    $build = [];

    // $path = $this->currPath->getPath();
    // $arg = explode('/', $path);
    // $textid = $arg[2];
    // $sourceid = 10589;
    // global $_SERVER;
    // $response = NULL;
    // // todo get the sourceid from the textid
    // if (isset($_GET['metadata'])) {
    //   $metadata = $_GET['metadata'];
    //   $build = [];
    //   $param['metadata'] = $metadata;
    //   $sourceid = 10589;.
    // $response =  my_module_reponse('http://' . $_SERVER['HTTP_HOST'] . '/api/source/' . $sourceid . '/status', 'GET', $param);
    // if ($response) {
    //      $result = json_decode($response, TRUE);
    //       //print("<pre>");print_r($result);exit;
    // $build['#markup'] = render($result);
    // } // if response
    // } // if metadata
    // Else {
    $build['form'] = $builtForm;
    // }
    $build['#cache']['max-age'] = 0;
    return $build;

  }

}
