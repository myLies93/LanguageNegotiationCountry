<?php

namespace Drupal\country_lang_negotiation\Plugin\LanguageNegotiation;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\language\LanguageNegotiationMethodBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class for identifying language via URL prefix and country code.
 *
 * @LanguageNegotiation(
 *   id = \Drupal\country_lang_negotiation\Plugin\LanguageNegotiation\LanguageNegotiationCountry::METHOD_ID,
 *   types = {\Drupal\Core\Language\LanguageInterface::TYPE_INTERFACE,
 *   \Drupal\Core\Language\LanguageInterface::TYPE_CONTENT,
 *   \Drupal\Core\Language\LanguageInterface::TYPE_URL},
 *   name = @Translation("Country"),
 *   description = @Translation("Language from the URL by country"),
 * )
 */
class LanguageNegotiationCountry extends LanguageNegotiationMethodBase implements InboundPathProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The language negotiation method id.
   */
  const METHOD_ID = 'language-country';

  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
  private $entityTypeManager;

  /**
   * LanguageNegotiationCountry constructor.
   *
   * @param $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param $plugin_id
   *   The plugin_id for the plugin instance.
   * @param $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(Request $request = NULL) {
    $langcode = NULL;

    if ($request && $this->languageManager) {
      $languages = $this->languageManager->getLanguages();
      $request_path = urldecode(trim($request->getPathInfo(), '/'));
      $path_args = explode('/', $request_path);
      $prefix = array_shift($path_args);
      $prefix = explode('-', $prefix);
      if (count($prefix) == 2) {
        $country_code_raw = array_shift($prefix);
        $lang_code_raw = array_shift($prefix);

        $negotiated_language = FALSE;
        foreach ($languages as $language) {
          if ($language->getId() === $lang_code_raw) {
            $negotiated_language = $language;
          }
        }

        $negotiated_country = $this->entityTypeManager
          ->getStorage('taxonomy_term')
          ->loadByProperties(['field_country_code' => $country_code_raw]);

        if ($negotiated_country && $negotiated_language) {
          $langcode = $negotiated_language->getId();
        }
        else {
          throw new NotFoundHttpException();
        }
      }
    }

    return $langcode;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $parts = explode('/', trim($path, '/'));
    $prefix = array_shift($parts);
    $path_items = explode('-', $prefix);
    if (count($path_items) == 2) {
      $path = '/' . implode('/', $parts);
    }

    return $path;
  }

}
