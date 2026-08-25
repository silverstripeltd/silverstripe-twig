<?php

namespace Azt3k\SS\Twig;

use AllowDynamicProperties;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTP;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ThemeResourceLoader;
use SilverStripe\View\ViewableData;
use SilverStripe\Control\Director;
use Swift_Message;
use Swift_MimePart;
use Symfony\Component\Mailer\MailerInterface;

#[AllowDynamicProperties]
class TwigEmail extends Email
{
    use TwigRenderer;

    public function AbsoluteLink($path) {
        return trim(Director::absoluteBaseURL(), '/') . $path;
    }


    /**
     * Over-ride the send function so that we can customise the
     * rendering of the email.
     *
     * @return void
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function send(): void
    {
        $this->render();
        Injector::inst()->get(MailerInterface::class)->send($this);
    }

    /**
     * Render the email - this
     * @param bool $plainOnly Only render the message as plain text
     * @return $this
     */
    private function render($plainOnly = false)
    {

        $htmlBody = $this->getHtmlBody();
        $plainBody = $this->getTextBody();

        // Ensure we can at least render something
        $htmlTemplate = $this->getHTMLTemplate();
        $plainTemplate = $this->getPlainTemplate();
        if (!$htmlTemplate && !$plainTemplate && !$plainBody && !$htmlBody) {
            return;
        }

        $htmlRender = null;
        $plainRender = null;

        if ($htmlBody) {
            $htmlRender = $htmlBody;
        }

        if ($plainBody) {
            $plainRender = $plainBody;
        }

        // Do not interfere with emails styles
        Requirements::clear();

        // Remove Sender key from the data
        $tplData = $this->getData();
        if (is_object($tplData)) {
            unset($tplData->Sender);
        }
        if (is_array($tplData)) {
            unset($tplData['Sender']);
        }

        // Render plain
        if (!$plainRender && $plainTemplate) {
            $plainRender = $this->renderWith($plainTemplate, $tplData)->Plain();
        }

        // Render HTML
        if (!$htmlRender && $htmlTemplate) {
            $htmlRender = $this->renderWith($htmlTemplate, $tplData);// ->RAW();
        }

        // Rendering is finished
        Requirements::restore();

        // Plain render fallbacks to using the html render with html tags removed
        if (!$plainRender && $htmlRender) {
            // call html_entity_decode() to ensure any encoded HTML is also stripped inside ->Plain()
            $dbField = DBField::create_field('HTMLFragment', html_entity_decode($htmlRender));
            $plainRender = $dbField->Plain();
        }

        // Handle edge case where no template was found
        if (!$htmlRender && $htmlBody) {
            $htmlRender = $htmlBody;
        }

        if (!$plainRender && $plainBody) {
            $plainRender = $plainBody;
        }

        if ($plainRender) {
            $this->text($plainRender);
        }
        if ($htmlRender && !$plainOnly) {
            $this->html($htmlRender);
        }
    }

}
