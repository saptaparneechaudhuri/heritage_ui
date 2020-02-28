<?php

namespace Drupal\heritage_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\node\Entity\Node;

/**
 *
 */
class HeritageTextContent extends ControllerBase {

  /**
   *
   */

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
  public function __construct(CurrentPathStack $currPath, LinkGeneratorInterface $pathLink) {

    $this->currPath = $currPath;
    $this->pathLink = $pathLink;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    return new static(

      $container->get('path.current'),
      $container->get('link_generator')
    );
  }

  /**
   *
   */
  public function getContent($textid = NULL) {
    $current_user = \Drupal::currentUser();
    if (in_array('editor', $current_user->getRoles()) || in_array('administrator', $current_user->getRoles())) {
      $allow_edit = 1;
    }
    else {
      $allow_edit = 0;
    }
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);

    $textid = $arg[2];
    // Load the node.
    $node = Node::load($textid);
    // Find the number of levels for this textid.
    $level_labels = explode(',', $node->field_level_labels->value);
    $numLevels = count($level_labels);

   

    

    global $_SERVER;
    $result = [];
    $response = NULL;
    $build = [];
    $params['metadata'] = 0;
   // $params['mool_shloka'] = 0;

    $play_option = [];
    if (isset($_GET['source'])) {
      $list = $_GET['source'];
      $fields = explode(',', $list);
      foreach ($fields as $field_name) {
        $params[$field_name] = 1;
      }
    }
    // See the play options.
    if (isset($_GET['play'])) {
      $list = $_GET['play'];
      $options = explode(',', $list);
      foreach ($options as $value) {
        $play_option[$value] = 1;
      }
    }
    if (isset($_GET['check'])) {
      $get = $_GET['check'];
    }
    else {
      $get = 'No GET Parameter';
    }   
    for ($j = 0; $j < $numLevels; $j++) {
      if ($j == 0) {
        $position = '1';

      }
      else {
        $position = $position . '.' . '1';
      }
    }

    // print_r($position);exit;
    if (isset($position)) {
      $params['position'] = $position;

    }
    // print_r($params['position']);exit;.

    $response = my_module_reponse('http://' . $_SERVER['HTTP_HOST'] . '/api/' . $textid, 'GET', $params);
    if ($response) {
      $result = json_decode($response, TRUE);
      // Add the audio play option to the result array.
      if (isset($play_option)) {
        $result['play'] = $play_option;
      }

      // print("<pre>");print_r($result);exit;
    }
    $metadata_form = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\Metadata');
    $result['lastlevel'] = strtolower(end($level_labels));
    $build = [
      '#theme' => 'text_content',
      '#data' => $result,
      '#textid' => $textid,
      '#metadata_form' => $metadata_form,
      '#allow_edit' => $allow_edit,
    ];
    return $build;
  }

  /**
   * Get the Name of the text.
   */
  public function getTitle($textid = NULL) {
    // Load the text name.
    $title = db_query("SELECT title FROM `node_field_data` WHERE nid = :textid AND type = :type", [':textid' => $textid, ':type' => 'heritage_text'])->fetchField();
    return $title;
  }

}
