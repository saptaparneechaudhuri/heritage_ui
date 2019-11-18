<?php

namespace Drupal\heritage_ui\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class HeritageTextPage extends ControllerBase {

  /**
   *
   */
  public function getList($textid = NULL) {

    $response = $result = NULL;
    $build = [];

    $params['metadata'] = 0;

    if (isset($_GET['position']) && isset($_GET['language'])) {

      $position = $_GET['position'];

      $language = $_GET['language'];
      $params['position'] = $position;
      $params['language'] = $language;

    }

    if (isset($_GET['source'])) {
      // $field_name = $_GET['source'];
      $list = $_GET['source'];
      $fields = explode(',', $list);

      // print_r($fields);exit;
      foreach ($fields as $field_name) {
        $params[$field_name] = 1;

      }

    }

    // print("<pre>");print_r($params);exit;
    if (isset($params[$field_name]) && isset($params['position']) && isset($params['language'])) {
      // print("<pre>");print_r($params);
      $response = my_module_reponse('http://172.27.13.38/api/' . $textid, 'GET', $params);

    }

    // print("<pre>");print_r($response);exit;
    if ($response) {
      $result = json_decode($response, TRUE);
      // print("<pre>");print_r($result);exit;
      $data = [];
      // # add all the data in one multiple dim array
      $data['title'] = 'Check TWIG Template';
      $data['users'] = $result;

      // Display the content in the middle section of the page.
      // print("<pre>");print_r($data);exit;
      $build = [
        '#theme' => 'heritage_ui',
      // Assign the page title.
        '#title' => 'contents from REST API',
      // Assign the string message like this.
        '#pagehtml' => 'data is coming from :/api/source/{sourceid}/status',
      // Assign the array like this to access in twig file.
        '#data' => $result,
      // If you need access to array inside json response
        // '#data' => $data,.
      ];

    }
    return $build;

  }

}
