<?php

namespace Drupal\heritage_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
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

    $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();
    $current_user = \Drupal::currentUser();
    // Get the user id.
    $user_id = $current_user->id();
    // Find out what sources are saved by the user.
    if (isset($user_id) && $user_id > 0) {

      $db = \Drupal::database();

      $sources_check = db_query("SELECT * FROM `heritage_users_data` WHERE user_id = :userid AND text_id = :textid", [':userid' => $user_id, ':textid' => $textid])->fetchAll();
      if (isset($sources_check)) {
        // print_r("sources present");exit;.
        foreach ($sources_check as $s) {
          $sources_present[] = $s->source_id;
        }

        // Get the format for the source.
        foreach ($sources_present as $sid) {

          $format = db_query("SELECT format FROM `heritage_source_info` WHERE id =:sourceid AND text_id = :textid", [':sourceid' => $sid, ':textid' => $textid])->fetchField();
          // Construct the field name.
          $field_name = 'field_' . $textname . '_' . $sid . '_' . $format;
          // print_r($field_name);exit;
          $params[$field_name] = 1;
        }

      }
    }
    // Allow edit for admin and editors.
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
     // print_r("I have a source");exit;
      $list = $_GET['source'];
      //print_r($list);exit;
      $fields = explode(',', $list);
      foreach ($fields as $field_name) {
        
        $params[$field_name] = 1;
      }
    }
   // print("<pre>");print_r($params);exit;

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
    // print_r($params[$field_name]);exit;
    // For anonymous users, user_id == 0
    //  if(isset($user_id)) {.
    $response = my_module_reponse('http://' . $_SERVER['HTTP_HOST'] . '/api/' . $textid, 'GET', $params);

    // }
    // $response = my_module_reponse('http://' . $_SERVER['HTTP_HOST'] . '/api/' . $textid, 'GET', $params);
    // print_r($response);exit;
    if ($response) {
      $result = json_decode($response, TRUE);
      // Add the audio play option to the result array.
      if (isset($play_option)) {
        $result['play'] = $play_option;
      }

  //  print("<pre>");print_r($result);exit;
    }
    $metadata_form = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\Metadata');
    // Audio Form.
    $audio_options_form = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\AudioPlay');
    $result['lastlevel'] = strtolower(end($level_labels));
    $build = [
      '#theme' => 'text_content',
      '#data' => $result,
      '#textid' => $textid,
      '#metadata_form' => $metadata_form,
      '#allow_edit' => $allow_edit,
      '#audio_options_form' => $audio_options_form,
      '#cache' => ['max-age' => 0], // Else the content will cache for anonymous users
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

  /**
   * Get the metadata of a source.
   */
  public function metadata($sourceid = NULL) {
    $meatadata = '';
    $metadata_string = db_query("SELECT metadata FROM `heritage_field_meta_data` WHERE id = :id", [':id' => $sourceid])->fetchField();
    $metadata_array = json_decode($metadata_string);
    foreach ($metadata_array as $key => $value) {
      $metadata = $metadata . $key . ': ' . $value . '<br>';
    }
    $build = [
      '#markup' => $this->t($metadata),
    ];
    return $build;
  }

}
