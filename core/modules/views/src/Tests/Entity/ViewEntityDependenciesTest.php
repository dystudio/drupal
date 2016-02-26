<?php

/**
 * @file
 * Contains \Drupal\views\Tests\Entity\ViewEntityDependenciesTest.
 */

namespace Drupal\views\Tests\Entity;

use Drupal\Component\Utility\Unicode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Tests\ViewKernelTestBase;
use Drupal\views\Views;
use Drupal\comment\Entity\CommentType;

/**
 * Tests the calculation of dependencies for views.
 *
 * @group views
 */
class ViewEntityDependenciesTest extends ViewKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_field_get_entity', 'test_relationship_dependency', 'test_plugin_dependencies', 'test_argument_dependency'];

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'comment', 'user', 'field', 'text', 'search'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp(FALSE);

    // Install the necessary dependencies for node type creation to work.
    $this->installEntitySchema('node');
    $this->installConfig(array('field', 'node'));

    $comment_type = CommentType::create(array(
      'id' => 'comment',
      'label' => 'Comment settings',
      'description' => 'Comment settings',
      'target_entity_type_id' => 'node',
    ));
    $comment_type->save();

    $content_type = NodeType::create([
      'type' => $this->randomMachineName(),
      'name' => $this->randomString(),
    ]);
    $content_type->save();
    $field_storage = FieldStorageConfig::create(array(
      'field_name' => Unicode::strtolower($this->randomMachineName()),
      'entity_type' => 'node',
      'type' => 'comment',
    ));
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $content_type->id(),
      'label' => $this->randomMachineName() . '_label',
      'description' => $this->randomMachineName() . '_description',
      'settings' => array(
        'comment_type' => $comment_type->id(),
      ),
    ])->save();
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'body'),
      'bundle' => $content_type->id(),
      'label' => $this->randomMachineName() . '_body',
      'settings' => array('display_summary' => TRUE),
    ])->save();

    ViewTestData::createTestViews(get_class($this), array('views_test_config'));
  }

  /**
   * Tests the getDependencies method.
   */
  public function testGetDependencies() {
    $expected = [];
    $expected['test_field_get_entity'] = [
      'module' => [
        'comment',
        'node',
        'user',
      ]
    ];
    // Tests dependencies of relationships.
    $expected['test_relationship_dependency'] = [
      'module' => [
        'comment',
        'node',
        'user',
      ]
    ];
    $expected['test_plugin_dependencies'] = [
      'module' => [
        'comment',
        'views_test_data',
      ],
      'content' => [
        'RowTest',
        'StaticTest',
        'StyleTest',
      ]
    ];

    $expected['test_argument_dependency'] = [
      'config' => [
        'core.entity_view_mode.node.teaser',
        'field.storage.node.body'
      ],
      'content' => [
        'ArgumentDefaultTest',
        'ArgumentValidatorTest'
      ],
      'module' => [
        'node',
        // The argument handler is provided by the search module.
        'search',
        'text',
        'user'
      ],
    ];

    foreach ($this::$testViews as $view_id) {
      $view = Views::getView($view_id);

      $dependencies = $view->getDependencies();
      $this->assertEqual($expected[$view_id], $dependencies);
      $config = $this->config('views.view.' . $view_id);
      \Drupal::service('config.storage.sync')->write($view_id, $config->get());
    }

    // Ensure that dependencies are calculated on the display level.
    $expected_display['default'] = [
      'config' => [
        'core.entity_view_mode.node.teaser',
      ],
      'content' => [
        'ArgumentDefaultTest',
        'ArgumentValidatorTest'
      ],
      'module' => [
        'core',
        'node',
        'search',
        'user',
        'views'
      ],
    ];
    $expected_display['page'] = [
      'config' => [
        'field.storage.node.body'
      ],
      'module' => [
        'core',
        'text',
        'views'
      ],
    ];

    $view = Views::getView('test_argument_dependency');
    $view->initDisplay();
    foreach ($view->displayHandlers as $display) {
      // Calculate the dependencies each display has.
      $this->assertEqual($expected_display[$display->getPluginId()], $display->calculateDependencies());
    }
  }

}