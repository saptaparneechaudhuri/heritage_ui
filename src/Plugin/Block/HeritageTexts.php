<?php

namespace Drupal\heritage_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;

/**
 * Provides a block to Display Heritage Texts, .
 *
 * @Block(
 *   id = "heritage_text_block",
 *   admin_label = @Translation("Display Heritage Texts"),
 *   category = @Translation("Custom")
 * )
 */
class HeritageTexts extends BlockBase implements ContainerFactoryPluginInterface {

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
    $build = [];

    $texts = '';
    $nodeid = 0;

    // Find the available heritage texts.
    $available_texts = db_query("SELECT * FROM `node_field_data` WHERE type = :type", [':type' => 'heritage_text'])->fetchAll();

    if (count($available_texts) > 0) {
      for ($i = 0; $i < count($available_texts); $i++) {
        // $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $available_texts[$i]->nid]);
        // Check if the page already exists.
        // TO DO, THE NODE ID IS HARDCODED HERE
        // FIND A METHOD TO LINK THE PAGE.
        $url = Url::fromRoute('heritage_ui.addpage', ['textid' => $available_texts[$i]->nid]);

        $link = $this->pathLink->generate($available_texts[$i]->title, $url);
        $texts = $texts . $link . '</br></br>';
        // $texts = $texts.$available_texts[$i]->entity_id.'</br>';
      }

    }
    $build['#markup'] = render($texts);
    $build['#cache']['max-age'] = 0;
    return $build;

    // Return [
    //    '#markup' => $this->t('Hello, World!'),
    //  ];.
  }

}
