<?php

namespace App\Service;

use App\Entity\Document;
use Dompdf\Dompdf;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Twig\Environment;

class TemplateService
{
    //private $request;
    private $templating;

    public function __construct(Environment $twig)
    {
        //$this->request = $request;
        $this->templating = $twig;
    }

    /* @todo request si reused so should be set in the constructor */

    public function render(Template $template, string $type)
    {
        $date = new \DateTime();
        $date = $date->format('Ymd_His');
        $response = new Response();

        switch ($type) {
            case 'application/ld+json':
            case 'application/json':
            case 'application/hal+json':
            case 'application/xml':
            case 'application/vnd.ms-word':
            case 'docx':
                $extension = 'docx';
                $file = $this->renderWord($template);
                $response->setContent($file);
                break;
            case 'pdf':
                $extension = 'pdf';
                $file = $this->renderPdf($template);
                $response->setContent($file);
                break;
            default:
                throw new BadRequestHttpException('Unsupported content type');
        }

        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$template->getName()}_{$date}.{$extension}");
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    public function renderWord(Template $template): ?string
    {
        $stamp = microtime();
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $this->getContent($template));
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');

        $filename = dirname(__FILE__, 3)."/var/{$template->getTemplateName()}_{$stamp}.docx";
        $objWriter->save($filename);

        return $filename;
    }

    public function renderPdf(Document $document, array $variables = []): ?string
    {
        /* ingewikkeld
        $stamp = microtime();
        // We start with a word file
        $filename = $this->renderWord($template);

        // And turn that into an pdf
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($filename);
        // Unset the orignal file
        unlink($filename); // deletes the temporary file

        $rendererName = Settings::PDF_RENDERER_DOMPDF;
        $rendererLibraryPath = realpath('../vendor/dompdf/dompdf');
        Settings::setPdfRenderer($rendererName, $rendererLibraryPath);
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'PDF');


        $filename = dirname(__FILE__, 3)."/var/{$template->getTemplateName()}_{$stamp}.pdf";
        $objWriter->save($objWriter);
        */

        // Simpel
        $dompdf = new Dompdf();
        $dompdf->loadHtml($this->getContent($document, $variables));
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Get the variables that can be used for rendering.
     *
     * @return array
     */
    public function getVariables(): array
    {
        $request = new Request();
        //todo use eavService->realRequestQueryAll(), maybe replace this function to another service than eavService?
        $query = $request->query->all();

        // @todo we want to support both json and xml here */
        $body = json_decode($request->getContent(), true);

        if ($body === null) {
            $body = [];
        }

        return array_merge($query, $body);
    }

    public function getContent(Document $document, array $variables = []): ?string
    {
        $variables = array_merge($variables, $this->getVariables());

        $type = $document->getType();

        //falbback
        if (!$type || !is_string($type)) {
            $type = 'twig';
        }

        $content = $document->getContent();
        if (!$content || !is_string($content)) {
            $content = 'no content found';
        }

        switch ($type) {
            case 'twig':
                $document = $this->templating->createTemplate($content);

                return $document->render($variables);
                break;
            case 'md':
                return $content;
            case 'rt':
                return $content;
            default:
                /* @todo throw error */
                return $content;
        }
    }
}
