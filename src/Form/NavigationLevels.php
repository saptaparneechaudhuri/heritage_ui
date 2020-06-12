<?php

namespace Drupal\heritage_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\CurrentPathStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Ajax\InvokeCommand;

/**
 *
 */
class NavigationLevels extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'heritage_ui_navigation_levels';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $textid = NULL) {
    // Get the textid from the current path.
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $levelToChange = [];
    $newParents = [];
    $textid = $arg[2];
    $form['#prefix'] = '<div id="navigationlevels">';
    $form['#suffix'] = '</div>';
    $form['text'] = [
      '#type' => 'hidden',
      '#value' => $textid,
    ];
    if (isset($textid) && $textid > 0) {
      // Find the textname.
      $textname = db_query("SELECT field_machine_name_value FROM `node__field_machine_name` WHERE entity_id = :textid", [':textid' => $textid])->fetchField();
      $form['textname'] = [
        '#type' => 'hidden',
        '#value' => $textname,
      ];
      // Query to find the number of levels of a textid.
      $levels = db_query("SELECT field_levels_value FROM `node__field_levels` WHERE entity_id = :textid and bundle = :bundle",
                [
                  ':textid' => $textid,
                  ':bundle' => 'heritage_text',
                ])->fetchField();
      $form['levels'] = [
        '#type' => 'hidden',
        '#value' => $levels,
      ];

      $level_labels = db_query("SELECT field_level_labels_value FROM `node__field_level_labels` WHERE entity_id = :textid and bundle = :bundle",
        [
          ':textid' => $textid,
          ':bundle' => 'heritage_text',
        ])->fetchField();
      $form['level_labels'] = [
        '#type' => 'hidden',
        '#value' => $level_labels,
      ];
      $level_labels_array = explode(',', $level_labels);

      $form['text_info'] = [
        '#type' => 'container',
        '#prefix' => '<div id="text-info">',
        '#suffix' => '</div>',
      ];
      $languages = \Drupal::service('language_manager')->getLanguages(LanguageInterface::STATE_CONFIGURABLE);
      if (isset($_GET['language'])) {
        $language = $_GET['language'];
      }
      else {
        $language = 'Devanagari';
      }
      foreach ($languages as $lang) {
        if ($language == $lang->getName()) {
          $langcode = $lang->getId();
        }
      }
      // Add a language field.
      $form['text_info']['selected_langcode'] = [
        '#type' => 'language_select',
        '#title' => $this->t('Language'),
        '#required' => TRUE,
        '#languages' => LanguageInterface::STATE_CONFIGURABLE | LanguageInterface::STATE_SITE_DEFAULT,
        '#default_value' => $langcode,
        '#ajax' => [
          'event' => 'change',
          'wrapper' => 'navigationlevels',
          'callback' => '::submitFormAjax',
        ],
      ];

      // If an ajax call is made, set the appropriate variables.
      if (!empty($form_state->getTriggeringElement())) {
        $triggeredBy = $form_state->getTriggeringElement()['#name'];
        if (isset($_POST['button_clicked'])) {
          $triggeredByArray = explode("_", $_POST['button_clicked']);
          // If the navigation buttons are clicked, do the following.
          if (isset($triggeredByArray[2]) && $triggeredByArray[2] == 'navigation') {
            $ajaxCalledBy = $triggeredByArray[2];
            $navLevleChange = $triggeredByArray[0];
            $navLevelChangeDirection = $triggeredByArray[1];
            $position_to_change = array_search(ucfirst($navLevleChange), $level_labels_array);
            $currentValue = $form_state->getValue(strtolower(end($level_labels_array)));
            $position = db_query("SELECT field_position_value FROM `taxonomy_term__field_position` WHERE entity_id = :tid", [':tid' => $currentValue])->fetchField();
            \Drupal::logger('current_value')->notice('Current Value: ' . $currentValue . ', Position: ' . $position);
            if ($navLevelChangeDirection == 'next') {
              // $default_value = $currentValue + 1;
              $newposition = $this->_get_next_level($textname, $position, 0, $position_to_change);
              $newposition_original = $newposition;
              $newposition_tid = $newposition;
              \Drupal::logger('new_postion_after_calc')->notice('New Position: ' . $newposition);
              for ($l = $levels - 1; $l >= 0; $l--) {
                $newParents[$l] = db_query("SELECT parent_target_id FROM `taxonomy_term__parent` WHERE entity_id = :tid", [':tid' => $newposition_tid])->fetchField();
                $newposition_tid = $newParents[$l];
              }
            }
            if ($navLevelChangeDirection == 'prev') {
              $newposition = $this->_get_prev_level($textname, $position, 0, $position_to_change);
              $newposition_original = $newposition;
              $newposition_tid = $newposition;
              for ($l = $levels - 1; $l >= 0; $l--) {
                $newParents[$l] = db_query("SELECT parent_target_id FROM `taxonomy_term__parent` WHERE entity_id = :tid", [':tid' => $newposition_tid])->fetchField();
                $newposition_tid = $newParents[$l];
              }
            }
            \Drupal::logger('new_parents_array')->notice('<pre><code>' . print_r($newParents, TRUE) . '</code></pre>');

          }
          else {
            $valueTriggered = $form_state->getTriggeringElement()['#value'];
            $keyTriggered = array_search(ucfirst($triggeredBy), $level_labels_array);
            if ($keyTriggered != $levels - 1) {
              for ($i = $keyTriggered + 1; $i < $levels; $i++) {
                $levelToChange[$i] = $level_labels_array[$i];
              }
            }
            else {
              $levelToChange[] = $level_labels_array[$keyTriggered];
            }
            \Drupal::logger('in_form_build_levels_to_change')->notice('<pre><code>' . print_r($levelToChange, TRUE) . '</code></pre>');
          }
        }
      }

      // Create the level fields.
      for ($j = 0; $j < $levels; $j++) {
        $levelName = strtolower($level_labels_array[$j]);
        $units = [];
        // If ($j != 0) {
        // If the drop down values are changed, get the no.of sublevels.
        // if (isset($levelToChange) && $level_labels_array[$j] == $levelToChange && $j != ($levels - 1)) {.
        if (count($levelToChange) > 0 && array_search($level_labels_array[$j], $levelToChange)) {
          if ($j == ($levels - 1)) {
            if ($keyTriggered == $j) {
              $id = $form_state->getValue(strtolower($level_labels_array[$j - 1]));
              $units = $this->get_sub_levels($id, $textname);
              $default_value = $valueTriggered;
            }
            else {
              // $units = $this->get_sub_levels($valueTriggered, $textname);
              if ($keyTriggered != $j - 1) {
                $id = $form_state->getValue(strtolower($level_labels_array[$j - 1]));
                \Drupal::logger('in_form_build_key_triggered_if')->notice('<pre><code>' . print_r($id, TRUE) . '</code></pre>');
              }
              else {
                $id = $valueTriggered;
                \Drupal::logger('in_form_build_key_triggered_else')->notice('<pre><code>' . print_r($id, TRUE) . '</code></pre>');
              }
              $units = $this->get_sub_levels($id, $textname);
              $first_element = key($units);
              $default_value = $first_element;
            }
          }
          else {
            if ($j != 0) {
              $id = $form_state->getValue(strtolower($level_labels_array[$j - 1]));
              $units = $this->get_sub_levels($id, $textname);
              $first_element = key($units);
              $default_value = $first_element;
            }
            else {
              $units = $this->get_sub_levels(0, $textname);
              $default_value = $valueTriggered;
            }
          }
        }
        else {
          // If there are no ajax calls, load the default values to form elements.
          if (empty($form_state->getTriggeringElement())) {
            if ($j != 0) {
              $id = $form_state->getValue(strtolower($level_labels_array[$j - 1]));
            }
            else {
              $id = 0;
            }
          }
          // Otherwise get the changed value and set the form fields appropriately.
          else {
            if ($j != 0) {
              $id = $form_state->getValue(strtolower($level_labels_array[$j - 1]));
            }
            else {
              $id = 0;
            }
          }
          $units = $this->get_sub_levels($id, $textname);
          $first_element = key($units);
          if (isset($ajaxCalledBy) && $ajaxCalledBy == 'navigation') {
            $units = $this->get_sub_levels($newParents[$j], $textname);
            if ($j != ($levels - 1)) {
              $default_value = $newParents[$j + 1];
            }
            else {
              $default_value = $newposition_original;
            }
          }
          elseif (count($levelToChange) == 0) {
            $default_value = $first_element;
          }
          else {
            $default_value = $form_state->getValue(strtolower($level_labels_array[$j]));
          }
        }
        $div = strtolower($level_labels_array[$j]) . '-wrapper';
        $formDiv = strtolower($level_labels_array[$j]) . '_wrapper';
        $form['text_info'][$formDiv] = [
          '#type' => 'container',
          '#prefix' => '<div id="' . $div . '">',
          '#suffix' => '</div>',
        ];
        $form['text_info'][$formDiv][$levelName] = [
          '#type' => 'select',
          // '#title' => $this->t('Select ' . $level_labels_array[$j]),
          '#required' => TRUE,
          '#options' => $units,
          '#id' => 'edit-' . $levelName,
            // '#value' => $default_value,
          '#default_value' => $default_value,
        ];
        $form_state->setValue($levelName, $default_value);
        $form['text_info'][$levelName . '_wrapper'][$levelName]['#ajax'] = [
          'event' => 'change',
          'wrapper' => 'navigationlevels',
          'callback' => '::submitFormAjax',
        ];
      }
    }
    // Navigation Buttons.
    $drupalSettings = 0;
    $form['navbuttons']['#prefix'] = '<div id="navigationButtons">';
    $form['navbuttons']['#suffix'] = '</div>';
    for ($l = $levels; $l > 0; $l--) {
      $levelName = strtolower($level_labels_array[$l - 1]);
      $textOnButton = '';
      for ($k = 1; $k <= $l; $k++) {
        $textOnButton = $textOnButton . '&lt;';
      }
      $label = $levelName . '_prev_navigation';

      $wrapper = $levelName . '_wrapper';
      $form['navbuttons'][$label] = [
        '#type' => 'html_tag',
        '#tag' => 'input',
        '#attributes' => [
          'type' => 'button',
          'value' => $this->t($textOnButton),
          'class' => 'button',
          'name' => $this->t($label),
          'id' => $this->t($label),
          'wrapper' => 'navigationlevels',
        ],
        '#attached' => [
          'library' => [
            'heritage_ui/heritage_ui_library',
          ],
        ],
      ];
      $form[$label]['#attached']['drupalSettings']['nav'][$drupalSettings] = $label;
      $form[$label]['#attached']['drupalSettings']['navLevel'][$drupalSettings] = $levelName;
      $drupalSettings++;
    }
    for ($l = 1; $l <= $levels; $l++) {
      $levelName = strtolower($level_labels_array[$l - 1]);
      $textOnButton = '';
      for ($k = 1; $k <= $l; $k++) {
        $textOnButton = $textOnButton . '&gt;';
      }
      $label = $levelName . '_next_navigation';

      $wrapper = $levelName . '_wrapper';
      $form['navbuttons'][$label] = [
        '#type' => 'html_tag',
        '#tag' => 'input',
        '#attributes' => [
          'type' => 'button',
          'value' => $this->t($textOnButton),
          'class' => 'button',
          'name' => $this->t($label),
          'id' => $this->t($label),
          'wrapper' => 'navigationlevels',
        ],
        '#attached' => [
          'library' => [
            'heritage_ui/heritage_ui_library',
          ],
        ],
      ];
      $form[$label]['#attached']['drupalSettings']['nav'][$drupalSettings] = $label;
      $form[$label]['#attached']['drupalSettings']['navLevel'][$drupalSettings] = $levelName;
      $drupalSettings++;
    }
    $form['navbuttons']['clicked'] = [
      '#type' => 'hidden',
      '#default_value' => 'default',
      '#name' => 'button_clicked',
      '#attributes' => [
        'id' => 'button-clicked',
      ],
    ];
    return $form;
  }

  /**
   *
   */
  public function submitFormAjax(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $level_labels = $values['level_labels'];
    $level_labels_array = explode(",", $level_labels);
    $levels = count($level_labels_array);
    $triggeredBy = $_POST['button_clicked'];
    if ($triggeredBy == 'default') {
      $triggeredBy = $form_state->getTriggeringElement()['#name'];
      if ($triggeredBy != $level_labels_array[$levels - 1]) {
        $response = $this->submitFormAjax2($form, $form_state);
      }
      else {
        $response = $this->submitFormAjax2($form, $form_state);
      }
    }
    else {
      $response = $this->submitFormAjax2($form, $form_state);
    }
    return $response;
  }

  /**
   *
   */
  public function submitFormAjax2(array &$form, FormStateInterface $form_state) {
    $newValue = [];
    $levelToChange = [];
    $values = $form_state->getValues();
    $level_labels = $values['level_labels'];
    $level_labels_array = explode(",", $level_labels);
    $levels = count($level_labels_array);
    $triggeredBy = $_POST['button_clicked'];
    $triggeredByArray = explode("_", $triggeredBy);
    $params = [];
    $position_array = [];
    $position = '';
    $path = $this->currPath->getPath();
    $arg = explode('/', $path);
    $textid = $arg[2];
    $textname = $values['textname'];
    $langcode = $values['selected_langcode'];
    $languages = \Drupal::service('language_manager')->getLanguages(LanguageInterface::STATE_CONFIGURABLE);
    foreach ($languages as $lang) {
      if ($langcode == $lang->getId()) {
        $language = $lang->getName();
      }
    }
    foreach ($values as $key => $value) {
      for ($i = 0; $i < $levels; $i++) {
        if (strtolower($key) == strtolower($level_labels_array[$i])) {
          \Drupal::logger('in_submit')->notice('Key is: ' . $key . ' Value is: ' . $value);
          if (isset($triggeredByArray[1]) && isset($triggeredByArray[2]) && $triggeredByArray[2] == 'navigation') {
            $newValue[] = $value;
            $levelToChange[] = '#edit-' . $key;
          }
          $position_value = db_query("SELECT field_position_value FROM  `taxonomy_term__field_position` WHERE entity_id = :entityid AND bundle = :textname", [':entityid' => $value, ':textname' => $textname])->fetchField();
          $position_array[] = $position_value;
        }
      }
    }
    \Drupal::logger('in_submit_position')->notice('<pre><code>' . print_r($position_array, TRUE) . '</code></pre>');
    $position = $position_array[$levels - 1];
    $params['position'] = $position;
    $params['language'] = $language;
    $params['metadata'] = 0;
    if (isset($_GET['source'])) {
      $list = $_GET['source'];
      $fields = explode(',', $list);
      foreach ($fields as $field_name) {
        $params[$field_name] = 1;
      }

    }
    else {
      $moolshloka_source_id = db_query("SELECT id FROM `heritage_source_info` WHERE text_id = :textid AND type = 'moolam' AND format = 'text'", [':textid' => $textid])->fetchField();
      $field_name = 'field_' . $textname . '_' . $moolshloka_source_id . '_text';
      $params[$field_name] = 1;

    }
    \Drupal::logger('in_submit')->notice('Position is: ' . $params['position']);
    if (isset($params[$field_name]) && isset($params['position']) && isset($params['language'])) {
      $result = my_module_reponse('http://' . $_SERVER['HTTP_HOST'] . '/api/' . $textid, 'GET', $params);
    }
    $result_json = json_decode($result, TRUE);
    if (isset($_GET['play'])) {
      $list = $_GET['play'];
      $options = explode(',', $list);
      foreach ($options as $value) {
        $play_option[$value] = 1;
      }
    }
    if (isset($play_option)) {
      $result_json['play'] = $play_option;
    }
    $result_json['lastlevel'] = strtolower(end($level_labels_array));
    $metadata_form = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\Metadata');
    // Add the audio play options form
    $audio_options_form = \Drupal::formBuilder()->getForm('Drupal\heritage_ui\Form\AudioPlay');
    $current_user = \Drupal::currentUser();
    if (in_array('editor', $current_user->getRoles()) || in_array('administrator', $current_user->getRoles())) {
      $allow_edit = 1;
    }
    else {
      $allow_edit = 0;
    }
    $build = [
      '#theme' => 'text_content',
      '#data' => $result_json,
      '#textid' => $textid,
      '#metadata_form' => $metadata_form,
      '#allow_edit' => $allow_edit,
      '#audio_options_form' => $audio_options_form,
      '#cache' => ['max-age' => 0],
    ];
    // $form_state->setRebuild();
    $response = new AjaxResponse();
    $response->addCommand(
      new ReplaceCommand('#textcontent', $build)
    );
    // $response->addCommand(new ReplaceCommand(NULL, $form));
    if (count($newValue) > 0) {
      // $form_state->setRebuild(TRUE);
      $response->addCommand(new ReplaceCommand(NULL, $form));
      for ($i = 0; $i < count($newValue); $i++) {
        $tmp[0] = $newValue[$i];
        $response->addCommand(new InvokeCommand($levelToChange[$i], 'val', $tmp));
      }
    }
    else {
      $response->addCommand(new ReplaceCommand(NULL, $form));
    }
    return $response;
  }

  /**
   *
   */
  public function _get_prev_level($textname, $position, $recursive, $position_to_change) {
    $newTid = 0;
    $position_array = explode(".", $position);
    $levels = count($position_array);
    if ($recursive == 1) {
      $newposition = $position;
    }
    else {
      $position_array[$position_to_change] = $position_array[$position_to_change] - 1;
      /* if ($position_to_change != ($levels-1)) {
      for ($i = $position_to_change+1; $i < $levels; $i++) {
      $position_array[$i] = 1;
      }
      } */
      $newposition = $this->_get_exact_position($position_array);
    }
    \Drupal::logger('get_prev_level')->notice('Next Position is: ' . $newposition);
    $newTid = db_query(
      "SELECT entity_id FROM `taxonomy_term__field_position` WHERE field_position_value = :newposition AND bundle = :bundle",
      [
        ':newposition' => $newposition,
        ':bundle' => $textname,
      ]
    )->fetchField();
    if ($newTid != NULL && $newTid > 0) {
      \Drupal::logger('get_prev_level')->notice('Next tid is: ' . $newTid . ', Next Position is: ' . $newposition);
      return $newTid;
    }
    else {
      if ($position_to_change != 0) {
        $position = $this->_get_last_level($textname, $position, $position_to_change);
      }
      else {
        // $position_array[$position_to_change] = $this->_get_last_level($textname, $position, $position_to_change);
        // $position = $this->_get_exact_position($position_array);
        $position = $this->_get_last_level($textname, $position, $position_to_change);
        \Drupal::logger('recursive_position_get_prev_level')->notice('Position in recursive is: ' . $position);
      }
      return $this->_get_prev_level($textname, $position, 1, $position_to_change);
    }
  }

  /**
   *
   */
  public function _get_next_level($textname, $position, $recursive, $position_to_change) {
    $newTid = 0;
    $position_array = explode(".", $position);
    $levels = count($position_array);
    $position_array[$position_to_change] = $position_array[$position_to_change] + 1;
    if ($position_to_change != ($levels - 1)) {
      for ($i = $position_to_change + 1; $i < $levels; $i++) {
        $position_array[$i] = 1;
      }
    }
    $newposition = $this->_get_exact_position($position_array);
    // \Drupal::logger('get_next_level')->notice('Next Position is: ' . $newposition);
    $newTid = db_query(
      "SELECT entity_id FROM `taxonomy_term__field_position` WHERE field_position_value = :newposition AND bundle = :bundle",
      [
        ':newposition' => $newposition,
        ':bundle' => $textname,
      ]
    )->fetchField();
    if ($newTid != NULL && $newTid > 0) {
      // \Drupal::logger('get_next_level')->notice('Next tid is: ' . $newTid . ', Next Position is: ' . $newposition);
      return $newTid;
    }
    else {
      if ($position_to_change != 0) {
        $position_to_change = $position_to_change - 1;
      }
      else {
        $position_array[$position_to_change] = 0;
        $position = $this->_get_exact_position($position_array);
        // \Drupal::logger('recursive_position')->notice('Position in recursive is: ' . $position);
      }
      return $this->_get_next_level($textname, $position, 1, $position_to_change);
    }
  }

  /**
   *
   */
  public function _get_exact_position($position_array) {
    $newposition = '';
    for ($j = 0; $j < count($position_array); $j++) {
      if ($j != (count($position_array) - 1)) {
        $newposition = $newposition . $position_array[$j] . '.';
      }
      else {
        $newposition = $newposition . $position_array[$j];
      }
    }
    return trim($newposition);
  }

  /**
   *
   */
  public function _get_last_level($textname, $position, $position_to_change) {
    $position_array = explode(".", $position);
    \Drupal::logger('_get_last_level')->notice('Position _get_last_level: ' . $position);
    \Drupal::logger('_get_last_level')->notice('Position to change: ' . $position_to_change);
    if ($position_to_change == 0) {
      $newposition = db_query("SELECT field_position_value FROM `taxonomy_term__field_position` WHERE bundle = :textname ORDER BY entity_id DESC LIMIT 1", [':textname' => $textname])->fetchField();
      if ($newposition == $position) {
        $newposition = db_query("SELECT field_position_value FROM `taxonomy_term__field_position` WHERE bundle = :textname ORDER BY entity_id ASC LIMIT 1", [':textname' => $textname])->fetchField();
      }
    }
    else {
      $position_array[$position_to_change - 1] = $position_array[$position_to_change - 1] - 1;
      \Drupal::logger('_get_last_level_new_position')->notice('<pre><code>' . print_r($position_array[$position_to_change - 1], TRUE) . '</code></pre>');
      \Drupal::logger('_get_last_level_new_position')->notice('<pre><code>' . print_r($position_array, TRUE) . '</code></pre>');
      if ($position_array[$position_to_change - 1] != 0) {
        $parentPositionArray = $position_array;
        array_pop($parentPositionArray);
        \Drupal::logger('_get_last_level_parent_position_array')->notice('<pre><code>' . print_r($parentPositionArray, TRUE) . '</code></pre>');
        $parentPosition = $this->_get_exact_position($parentPositionArray);
        \Drupal::logger('_get_last_level_parent_position')->notice('Parent Position is: ' . $parentPosition);
        $prevPositionParentTid = db_query(
          "SELECT entity_id FROM `taxonomy_term__field_position` WHERE field_position_value = :position AND bundle = :bundle",
          [
            ':position' => $parentPosition,
            ':bundle' => $textname,
          ]
        )->fetchField();
        \Drupal::logger('_get_last_level_new_position')->notice('Position Prent Tid: ' . $prevPositionParentTid . ', Position parent id:' . $position[$position_to_change - 1]);
        $newposition = db_query("SELECT field_position_value p FROM `taxonomy_term__field_position` WHERE entity_id IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :parent) ORDER BY entity_id DESC LIMIT 1", [':parent' => $prevPositionParentTid])->fetchField();
      }
      else {
        \Drupal::logger('_get_last_level_going_recursive')->notice('<pre><code>' . print_r($position, TRUE) . '</code></pre>');
        $position_to_change = $position_to_change - 1;
        return $this->_get_last_level($textname, $position, $position_to_change);
      }
    }
    \Drupal::logger('_get_last_level_new_position')->notice('Position _get_last_level: ' . $newposition);
    return $newposition;
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   *
   */
  public function get_sub_levels($tid, $textname) {
    $subLevels = [];
    if ($tid != 0) {
      $unitsInThisLevel = db_query("SELECT name, tid FROM `taxonomy_term_field_data` WHERE tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :tid) ORDER BY tid ASC",
      [
        ':tid' => $tid,
      ])->fetchAll();
    }
    else {
      $unitsInThisLevel = db_query("SELECT name, tid FROM `taxonomy_term_field_data` WHERE vid = :textname AND tid IN (SELECT entity_id FROM `taxonomy_term__parent` WHERE parent_target_id = :tid) ORDER BY tid ASC",
      [
        ':textname' => $textname,
        ':tid' => 0,
      ])->fetchAll();
    }
    foreach ($unitsInThisLevel as $value) {
      $subLevels[$value->tid] = $value->name;
    }

    return $subLevels;
  }

}
