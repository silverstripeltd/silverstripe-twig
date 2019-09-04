<?php

namespace Azt3k\SS\Twig;

use SilverStripe\Core\ClassInfo;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Director;
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
use Swift_Message;
use Swift_MimePart;


class TwigEmail extends Email
{
    use TwigRenderer;

    /**
     * Render the email
     * @param bool $plainOnly Only render the message as plain text
     * @return $this
     */
    public function render($plainOnly = false)
    {
        if ($existingPlainPart = $this->findPlainPart()) {
            $this->getSwiftMessage()->detach($existingPlainPart);
        }
        unset($existingPlainPart);

        // Respect explicitly set body
        $htmlPart = $plainOnly ? null : $this->getBody();
        $plainPart = $plainOnly ? $this->getBody() : null;

        // Ensure we can at least render something
        $htmlTemplate = $this->getHTMLTemplate();
        $plainTemplate = $this->getPlainTemplate();
        if (!$htmlTemplate && !$plainTemplate && !$plainPart && !$htmlPart) {
            return $this;
        }

        // Do not interfere with emails styles
        Requirements::clear();
        
        // Render plain part
        if ($plainTemplate && !$plainPart) {
            $plainPart = $this->renderWith($plainTemplate, $this->getData());
        }

        // Render HTML part, either if sending html email, or a plain part is lacking
        if (!$htmlPart && $htmlTemplate && (!$plainOnly || empty($plainPart))) {
            $htmlPart = $this->renderWith($htmlTemplate, $this->getData());
        }

        // Plain part fails over to generated from html
        if (!$plainPart && $htmlPart) {
            /** @var DBHTMLText $htmlPartObject */
            $htmlPartObject = DBField::create_field('HTMLFragment', $htmlPart);
            $plainPart = $htmlPartObject->Plain();
        }
        
        // Rendering is finished
        Requirements::restore();

        // Fail if no email to send
        if (!$plainPart && !$htmlPart) {
            return $this;
        }

        // Build HTML / Plain components
        if ($htmlPart && !$plainOnly) {
            $this->setBody($htmlPart);
            $this->getSwiftMessage()->setContentType('text/html');
            $this->getSwiftMessage()->setCharset('utf-8');
            if ($plainPart) {
                $this->getSwiftMessage()->addPart($plainPart, 'text/plain', 'utf-8');
            }
        } else {
            if ($plainPart) {
                $this->setBody($plainPart);
            }
            $this->getSwiftMessage()->setContentType('text/plain');
            $this->getSwiftMessage()->setCharset('utf-8');
        }

        return $this;
    }
}
