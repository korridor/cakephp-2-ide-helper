<?php

App::uses('AppShell', 'Console/Command');

/**
 * IdeHelperShell.
 *
 * @version 1.0
 *
 * @author Korridor, Mark Scherer
 * @license http://opensource.org/licenses/mit-license.php MIT
 */
class IdeHelperShell extends AppShell
{
    /**
     * @var array
     */
    private $plugins = null;

    /**
     * @var string
     */
    private $content = '';

    /**
     * @var string
     */
    private $filename;

    /**
     * @var string
     */
    private $path;

    public function main()
    {
        $this->out('Started creating ide helper file');

        $this->filename = '_ide_helper.php';
        $this->path = APP_DIR;

        $this->generateIdeHelpersForModels();
        $this->generateIdeHelpersForControllers();

        $this->writeFile();

        $this->out('Saved file: '.$this->path.'/'.$this->filename);
    }

    private function generateIdeHelpersForModels()
    {
        $files = $this->getModelFiles();
        $behaviourFiles = $this->getBehaviorFiles();

        $content = "\n";
        $content .= '/*** models start ***/'."\n";
        foreach ($files as $name) {
            $content .= '/**'."\n";
            if (!empty($files)) {
                $content .= $this->getCommentLinesForModels($files);
            }
            if (!empty($behaviourFiles)) {
                $content .= $this->getCommentLinesForBehaviors($behaviourFiles);
            }
            $content .= ' */'."\n";

            $content .= 'class '.$name.' extends AppModel {'."\n";
            $content .= '}'."\n";
        }
        $content .= '/*** models end ***/'."\n";

        $this->content .= $content;
    }

    private function generateIdeHelpersForControllers()
    {
        $content = "\n";
        $content .= '/*** controllers start ***/'."\n";
        $componentFiles = $this->getComponentFiles();
        $modelFiles = $this->getModelFiles();
        $controllerFiles = $this->getControllerFiles();
        foreach ($controllerFiles as $name) {
            $content .= '/**'."\n";
            if (!empty($componentFiles)) {
                $content .= $this->getCommentLinesForComponents($componentFiles);
            }
            if (!empty($modelFiles)) {
                $content .= $this->getCommentLinesForModels($modelFiles);
            }
            $content .= ' */'."\n";
            $content .= 'class '.$name.' extends AppController {'."\n";
            $content .= '}'."\n";
        }
        $content .= '/*** controllers end ***/'."\n";

        $this->content .= $content;
    }

    /**
     * @param string[] $modelNames
     *
     * @return string Comment lines
     */
    private function getCommentLinesForModels($modelNames)
    {
        $res = '';
        foreach ($modelNames as $name) {
            $res .= ' * @property '.$name.' $'.$name."\n";
        }

        return $res;
    }

    /**
     * @param string[] $behaviorNames
     *
     * @return string Comment lines
     */
    private function getCommentLinesForBehaviors($behaviorNames)
    {
        $res = '';
        foreach ($behaviorNames as $name) {
            if (!($varName = $this->getVarNameFromClassName($name, 'Behavior'))) {
                continue;
            }
            $res .= ' * @property '.$name.' $'.$varName."\n";
        }

        return $res;
    }

    /**
     * @param string[] $componentNames
     *
     * @return string Comment lines
     */
    private function getCommentLinesForComponents($componentNames)
    {
        $res = '';
        foreach ($componentNames as $name) {
            if (!($varName = $this->getVarNameFromClassName($name, 'Component'))) {
                continue;
            }
            $res .= ' * @property '.$name.' $'.$varName."\n";
        }

        return $res;
    }

    /**
     * @param string $className
     * @param string $type
     *
     * @return string
     */
    private function getVarNameFromClassName($className, $type)
    {
        if (false === ($pos = strrpos($className, $type))) {
            return ''; // TODO exception
        }

        return substr($className, 0, $pos);
    }

    /**
     * @return string[]
     */
    private function getModelFiles()
    {
        $files = $this->getFiles('Model');
        $appIndex = array_search('AppModel', $files);
        if (false !== $appIndex) {
            unset($files[$appIndex]);
        }
        $appIndex = array_search('Model', $files);
        if (false !== $appIndex) {
            unset($files[$appIndex]);
        }

        return $files;
    }

    /**
     * @return string[]
     */
    private function getControllerFiles()
    {
        $files = $this->getFiles('Controller');
        $appIndex = array_search('AppController', $files);
        if (false !== $appIndex) {
            unset($files[$appIndex]);
        }
        $appIndex = array_search('Controller', $files);
        if (false !== $appIndex) {
            unset($files[$appIndex]);
        }

        return $files;
    }

    /**
     * @return string[]
     */
    private function getBehaviorFiles()
    {
        $files = $this->getFiles('Model/Behavior');

        return $files;
    }

    /**
     * @return string[]
     */
    private function getComponentFiles()
    {
        $files = $this->getFiles('Model/Behavior');

        return $files;
    }

    /**
     * @param string $type
     *
     * @return string[]
     */
    private function getFiles($type)
    {
        $files = App::objects($type, null, false);
        $corePath = App::core($type);
        $coreFiles = App::objects($type, $corePath, false);
        $files = array_merge($coreFiles, $files);

        if (!isset($this->plugins)) {
            $this->plugins = CakePlugin::loaded();
        }

        if (!empty($this->plugins)) {
            foreach ($this->plugins as $plugin) {
                $pluginType = $plugin.'.'.$type;
                $pluginFiles = App::objects($pluginType, null, false);
                if (!empty($pluginFiles)) {
                    foreach ($pluginFiles as $file) {
                        if (false !== strpos($file, 'App'.$type)) {
                            //$this->appFiles[$file] = $plugin.'.'.$type;
                            continue;
                        }
                        $files[] = $file;
                    }
                }
            }
        }
        $files = array_unique($files);

        // no test/tmp files etc (helper.test.php or helper.OLD.php)
        foreach ($files as $key => $file) {
            if (false !== strpos($file, '.') || !preg_match('/^[\da-zA-Z_]+$/', $file)) {
                unset($files[$key]);
            }
        }

        return $files;
    }

    private function writeFile()
    {
        $content = '<?php'.PHP_EOL.PHP_EOL;
        $content .= '// Printed: '.date('d.m.Y, H:i:s').PHP_EOL;
        $content .= $this->content;

        file_put_contents(ROOT.DS.$this->path.DS.$this->filename, $content);
    }
}
