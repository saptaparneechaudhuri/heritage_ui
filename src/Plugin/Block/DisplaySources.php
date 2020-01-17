<?php

namespace Drupal\heritage_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;

/**
 * Provides a block to Display Sources of a Heritage Text, .
 *
 * @Block(
 *   id = "heritage_sources_menu",
 *   admin_label = @Translation("Sources of Heritage Text in Menu"),
 *   category = @Translation("Custom")
 * )
 */
class DisplaySources extends BlockBase implements ContainerFactoryPluginInterface {

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
   * {@inheritdoc}
   */
  public function build() {

    $builtForm = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\SourcesMenu');
    $build = [];
    // $sources = '';.
    // $path = $this->currPath->getPath();
    // $arg = explode('/', $path);
    // $textid = $arg[2];
    // // print_r($arg);exit;
    // // Fetch all the available sources.
    // $available_sources = db_query("SELECT * FROM `heritage_source_info` WHERE text_id = :textid ORDER BY language DESC", [':textid' => $textid])->fetchAll();
    // If (count($available_sources) > 0) {
    //   for ($i = 0; $i < count($available_sources); $i++) {
    //     $url = Url::fromRoute('entity.node.canonical', ['node' => $available_sources[$i]->id]);
    //     $link = $this->pathLink->generate($available_sources[$i]->title, $url);
    //     $sources = $sources . $link . '</br>';.
    // }
    // }.
    // $render = $sources;.
    $build['form'] = $builtForm;
    // $build['#markup'] = render($render);
    $build['#cache']['max-age'] = 0;
    return $build;

  }

}
