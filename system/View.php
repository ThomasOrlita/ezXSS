<?php

class View
{

    /**
     * @var [type]
     */
    private $content;
    /**
     * @var [type]
     */
    public $title;


    /**
     * Constructor which adds certain headers
     */
    public function __construct()
    {
        // Add security headers
        header('X-XSS-Protection: 1');
        //header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; font-src fonts.gstatic.com; script-src 'self' 'nonce-csrf';");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Makes a render of a page with only an error message
     *
     * @param string $message
     * @return string
     */
    public function renderErrorPage($message)
    {
        $this->content = '';
        $this->renderTemplate('system/error');
        $this->renderMessage($message);
        return $this->showContent();
    }

    /**
     * Updates the message template with the given message
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    public function renderMessage($message, $type = 'danger')
    {
        $content = $this->getContent();

        $content = str_replace('{message}', '<div class="alert alert-' . e($type) . '" role="alert">' . e($message) . '</div>', $content);

        $this->content = $content;
    }

    /**
     * Updates all the data parameters with the correct data
     *
     * @param string $param
     * @param string $value
     * @param string $plain
     * @return void
     */
    public function renderData($param, $value, $plain = false)
    {
        $content = $this->getContent();

        if($plain) {
            $content = str_replace('{%data ' . $param . '}', $value, $content);
        } else {
            $content = str_replace('{%data ' . $param . '}', e($value), $content);
        }

        $this->content = $content;
    }

    /**
     * Updates all if statements in the view template based on the given boolean
     *
     * @param string $condition
     * @param bool $bool
     * @return void
     */
    public function renderCondition($condition, $bool)
    {
        $content = $this->getContent();

        preg_match_all('/{%if ' . $condition . '}(.*?){%\/if}/s', $content, $matches);
        foreach ($matches[1] as $key => $value) {
            // Shows the content if the given boolean is true
            if ($bool === true) {
                $content = str_replace(
                    $matches[0][$key],
                    $matches[1][$key],
                    $content
                );
                // Removes the content block when false
            } else {
                $content = str_replace(
                    $matches[0][$key],
                    '',
                    $content
                );
            }
        }
        $this->content = $content;
    }

    /**
     * Renders a dataset in a foreach block, for example for in tables
     *
     * @param string $name
     * @param array $data
     * @return void
     */
    public function renderDataset($name, $data)
    {
        $content = $this->getContent();

        // Finds all foreach blocks in a page
        preg_match_all('/{%foreach ' . $name . '}(.*?){%\/foreach}/s', $content, $blockMatches);
        foreach ($blockMatches[1] as $blockKey => $blockValue) {
            $template = $blockMatches[1][$blockKey];
            $htmlDump = '';

            // Find all parameters in the block
            preg_match_all('/{' . $name . '->(.*?)}/', $template, $templateMatches);

            foreach ($data as $item) {
                $template = $blockMatches[1][$blockKey];
                foreach ($templateMatches[1] as $templateKey => $templateValue) {
                    // Replace all parameters with the correct values from $data
                    $template = str_replace(
                        $templateMatches[0][$templateKey],
                        e($item[$templateMatches[1][$templateKey]]),
                        $template
                    );
                }
                $htmlDump .= $template;
            }

            // Makes an complete new block with all data
            $content = str_replace(
                $blockMatches[0][$blockKey],
                $htmlDump,
                $content
            );
        }

        $this->content = $content;
    }

    /**
     * Renders the content of a function given
     *
     * @param string $view
     * @return void
     */
    public function renderTemplate($view)
    {
        $content = $this->surroundBody($view);

        // Search and replace template content
        preg_match_all('/{(.*?)\[(.*?)]}/', $content, $matches);
        foreach ($matches[1] as $key => $value) {
            if (method_exists($this, $value)) {
                $content = str_replace(
                    $matches[0][$key],
                    e($this->$value((string)($matches[2][$key]))),
                    $content
                );
            }
        }

        $this->content = $content;
        $this->renderCondition('userIsAdmin', $this->session('rank') == 7);
    }

    public function renderPayload($payload)
    {
        $content = $this->getPayload($payload);

        // Search and replace payload content
        preg_match_all('/{{(.*?)}}/', $content, $matches);
        foreach ($matches[1] as $key => $value) {
            if (method_exists($this, $value)) {
                $content = str_replace(
                    $matches[0][$key],
                    e($this->$value()),
                    $content
                );
            }
        }

        $this->content = $content;
    }

    public function getPayload($payload)
    {
        $file = __DIR__ . "/../app/views/payloads/$payload.js";
        if (!is_file($file)) {
            throw new Exception('Payload not found');
        }
        return file_get_contents($file);
    }

    /**
     * Surrounds the body content with the header and footer
     *
     * @param string $view
     * @return string
     */
    public function surroundBody($view)
    {
        $content  = $this->getHtml('system/header');
        $content .= $this->getHtml($view);
        $content .= $this->getHtml('system/footer');
        $content = str_replace('{menu}', $this->getHtml('system/menu'), $content);
        return $content;
    }

    /**
     * Returns HTML block of the given view
     *
     * @param string $file
     * @return string
     */
    private function getHtml($file)
    {
        $file = __DIR__ . "/../app/views/$file.html";
        if (is_file($file)) {
            return file_get_contents($file);
        }
        return '404';
    }

    /**
     * Last function in controller to return all content from how its build
     *
     * @return void
     */
    public function showContent()
    {
        return str_replace('{message}', '', $this->content);
    }

    /**
     * Returns correct page title with site name
     *
     * @return void
     */
    public function title()
    {
        return 'ezXSS ~ ' . $this->title;
    }

    /**
     * Get's session data
     *
     * @param string $param
     * @return string
     */
    public function session($param)
    {
        return isset($_SESSION[$param]) ? e($_SESSION[$param]) : '';
    }

    /**
     * Returns current content
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Returns title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Returns ezXSS version
     *
     * @return string
     */
    public function version()
    {
        return e(version);
    }

    public function theme()
    {
        return ''; // todo
    }

    public function currentPage($page)
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $uriParts = explode('/', $uri);

        if (strpos($page, '/') !== false && isset($uriParts[3]) && $uriParts[2] . '/' . $uriParts[3] == $page) {
            return 'menu-active';
        }
        if (isset($uriParts[2]) && $uriParts[2] == $page && (!isset($uriParts[3]) || empty($uriParts[3]))) {
            return 'menu-active';
        }
        return '';
    }

    /**
     * Returns domain
     *
     * @return string
     */
    public function domain()
    {
        return e($_SERVER['SERVER_NAME']);
    }

    /**
     * Set's title
     *
     * @param string $title
     * @return void
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Set's content type
     *
     * @return string
     */
    public function setContentType($type)
    {
        header('Content-Type: ' . $type);
    }
}
