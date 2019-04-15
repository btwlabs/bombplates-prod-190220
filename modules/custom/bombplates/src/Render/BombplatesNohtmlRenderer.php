<?php

/**
 * @file - alternate no-html render - can optionally render NO wrapping elements, minimal modal CSS, or all headers but no excess html
 */

namespace Drupal\bombplates\Render;

class BombplatesNohtmlRenderer implements \Drupal\Core\Render\BareHtmlPageRendererInterface {
  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The HTML response attachments processor service.
   *
   * @var \Drupal\Core\Render\AttachmentsResponseProcessorInterface
   */
  protected $htmlResponseAttachmentsProcessor;

  /**
   * Constructs a new BareHtmlPageRenderer.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Render\AttachmentsResponseProcessorInterface $html_response_attachments_processor
   *   The HTML response attachments processor service.
   */
  public function __construct(\Drupal\Core\Render\RendererInterface $renderer, \Drupal\Core\Render\AttachmentsResponseProcessorInterface $html_response_attachments_processor) {
    $this->renderer = $renderer;
    $this->htmlResponseAttachmentsProcessor = $html_response_attachments_processor;
  }

  /**
   * {@inheritdoc}
   */
  public function renderNohtml(array $content, $nothtml = FALSE, $inc_headers = TRUE) {
    $this->renderer->renderRoot($content);
    $response = new \Drupal\Core\Render\HtmlResponse();
    $response->setContent($content);
    // Process attachments, because this does not go via the regular render
    // pipeline, but will be sent directly.
    $response = $this->htmlResponseAttachmentsProcessor->processAttachments($response);
    return $response;
  } // renderNohtml

  /**
   * {@inheritdoc}
   */
  public function renderBarePage(array $content, $title, $page_theme_property, array $page_additions = []) {
    $nothtml = \Drupal::request()->query->get('nohtml', FALSE);
    $inc_headers = \Drupal::request()->query->get('inc_headers', FALSE);
    return renderNohtml($content, $nothtml, $inc_headers);
  } // renderBarePage
} // BombplatesNohtmlRenderer
