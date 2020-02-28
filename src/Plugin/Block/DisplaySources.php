<?php

namespace Drupal\heritage_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;

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
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];
    $url = Url::fromRoute('heritage_ui.sourcesmenu', ['textid' => $textid]);

    // This is to keep the check boxes ticked.
    if (isset($_GET['source'])) {
      $url->setOption('query',[
           'source' => $_GET['source'],
       ]);
    }
    $build['link'] = [
      '#title' => 'Select Sources',
      '#type' => 'link',
      '#url' => $url,
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-renderer' => 'off_canvas',
        'data-dialog-options' => Json::encode(['width' => 400]),
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];
    $build['#cache']['max-age'] = 0;
    /* return $build; 
    $build['form'] = $builtForm;
    $build['#cache']['max-age'] = 0; */
    return $build;
  }

}
