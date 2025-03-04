<?php

namespace WPMVC\Commands\Traits;

use Exception;
use Ayuco\Coloring;
use Ayuco\Exceptions\NoticeException;
use Gettext\Translations;
use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use TenQuality\Gettext\Scanner\WPJsScanner;
use TenQuality\Gettext\Scanner\WPPhpScanner;

/**
 * Generates POT file.
 *
 * @author Alejandro Mostajo <http://about.me/amostajo>
 * @copyright 10Quality <http://www.10quality.com>
 * @license MIT
 * @package WPMVC\Commands
 * @version 1.1.18
 */
trait GeneratePotTrait
{
    /**
     * Generate pot file.
     * @since 1.1.0
     * 
     * @param string $lang Pot default language.
     */
    protected function generatePot($lang = 'en')
    {
        try {
            $domain = $this->config['localize']['textdomain'];
            $translations = Translations::create($domain, $lang);
            $filename = $this->config['localize']['path'].$domain.'.pot';
            $is_update = file_exists($filename);
            // Prepare headers
            $translations->getHeaders()->set('Project-Id-Version', $this->config['namespace']);
            $translations->getHeaders()->set('POT-Creation-Date', date('Y-m-d H:i:s'));
            $translations->getHeaders()->set('MIME-Version', $this->config['version']);
            $translations->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');
            $translations->getHeaders()->set('Last-Translator', $this->config['author']);
            $translations->getHeaders()->set('X-Generator', 'WordPress MVC Commands 1.1');
            // Php files
            $scanner = new WPPhpScanner(
                Translations::create($domain)
            );
            foreach (glob($this->rootPath.'*.php') as $file) {
                if ($this->isFileToLocalizeExcluded($file))
                    continue;
                $scanner->scanFile($file);
            }
            foreach (glob($this->getAppPath().'*.php') as $file) {
                if ($this->isFileToLocalizeExcluded($file))
                    continue;
                $scanner->scanFile($file);
            }
            foreach (glob($this->getAppPath().'**/*.php') as $file) {
                if ($this->isFileToLocalizeExcluded($file))
                    continue;
                $scanner->scanFile($file);
            }
            foreach (glob($this->getViewsPath().'*.php') as $file) {
                if ($this->isFileToLocalizeExcluded($file))
                    continue;
                $scanner->scanFile($file);
            }
            foreach (glob($this->getViewsPath().'**/*.php') as $file) {
                if ($this->isFileToLocalizeExcluded($file))
                    continue;
                $scanner->scanFile($file);
            }
            $scannedTranslations = $scanner->getTranslations();
            if (array_key_exists($domain, $scannedTranslations))
                $translations = $translations->mergeWith($scannedTranslations[$domain]);
            // Js files
            $scanner = new WPJsScanner(
                Translations::create($domain)
            );
            foreach (glob($this->getAssetsPath().'js/*.js') as $file) {
                if ($this->isFileToLocalizeExcluded($file))
                    continue;
                $scanner->scanFile($file);
            }
            foreach (glob($this->getAssetsPath().'js/**/*.js') as $file) {
                if ($this->isFileToLocalizeExcluded($file))
                    continue;
                $scanner->scanFile($file);
            }
            $scannedTranslations = $scanner->getTranslations();
            if (array_key_exists($domain, $scannedTranslations))
                $translations = $translations->mergeWith($scannedTranslations[$domain]);
            // Prepare
            if (!is_dir($this->config['localize']['path']))
                mkdir($this->config['localize']['path'], 0777, true);
            // Write pot file
            $generator = new PoGenerator();
            $output = $generator->generateString($translations, $filename);
            $output = str_replace($this->rootPath, '', $output);
            $output = str_replace('#: \assets', '#: /assets', $output);
            if ($is_update)
                unlink($filename);
            file_put_contents($filename, $output);
            // Print end
            $this->_print_success($is_update ? 'POT file updated!' : 'POT file generated!');
            $this->_lineBreak();
        } catch (Exception $e) {
            error_log($e);
            throw $e;
        }
    }

    /**
     * Exclude files from being scanned.
     * @since 1.1.18
     * 
     * @param string $file
     * 
     * @return bool
     */
    protected function isFileToLocalizeExcluded($file)
    {
        $exclusions = array_key_exists('localize',$this->config)
            && array_key_exists('translations',$this->config['localize'])
            && array_key_exists('file_exclusions',$this->config['localize']['translations'])
            && !empty($this->config['localize']['translations']['file_exclusions'])
            && is_array($this->config['localize']['translations']['file_exclusions'])
                ? $this->config['localize']['translations']['file_exclusions']
                : null;
        if (!empty($exclusions))
            foreach($exclusions as $exclusion) {
                if (strpos($file,$exclusion) !== false)
                    return true;
            }
        return false;
    }

    /**
     * Generate PO file.
     * @since 1.1.0
     * 
     * @param string $locale PO locale.
     * @param string $lang   Pot default language.
     */
    protected function generatePo($locale, $lang = 'en')
    {
        try {
            $domain = $this->config['localize']['textdomain'];
            // Do we have a POT file?
            $pot_filename = $this->config['localize']['path'].$domain.'.pot';
            $this->generatePot($lang);
            // Does PO file already exist?
            $po_filename = $this->config['localize']['path'].$domain.'-'.$locale.'.po';
            $translations = null;
            $to_update = false;
            $loader = new PoLoader;
            $clear = array_key_exists('clear', $this->options);
            if (!$clear && file_exists($po_filename)) {
                $translations = $loader->loadFile($po_filename);
                $translations = $translations->mergeWith($loader->loadFile($pot_filename));
                $to_update = true;
                $translations->getHeaders()->set('PO-Update-Date', date('Y-m-d H:i:s'));
            } else {
                $translations = $loader->loadFile($pot_filename);
                $translations->getHeaders()->set('PO-Creation-Date', date('Y-m-d H:i:s'));
            }
            $translations->getHeaders()->setLanguage($locale);
            // Prepare
            if (!is_dir($this->config['localize']['path']))
                mkdir($this->config['localize']['path'], 0777, true);
            // Write pot file
            $generator = new PoGenerator();
            $generator->generateFile($translations, $po_filename);
            // Print end
            $this->_print_success('PO:'.$locale.($to_update ? ' file updated!' : ' file generated!'));
            $this->_lineBreak();
        } catch (Exception $e) {
            error_log($e);
            throw $e;
        }
    }

    /**
     * Generate MO file.
     * @since 1.1.0
     * 
     * @param string $locale PO locale.
     */
    protected function generateMo($locale)
    {
        try {
            $domain = $this->config['localize']['textdomain'];
            // Filenames
            $po_filename = $this->config['localize']['path'].$domain.'-'.$locale.'.po';
            // Handle PO
            if (!file_exists($po_filename))
                throw new NoticeException('PO:'.$locale.' file doesn\'t exists, nothing to generate.');
            $loader = new PoLoader;
            $translations = $loader->loadFile($po_filename);
            // Write pot file
            $generator = new MoGenerator();
            $generator->generateFile($translations, $this->config['localize']['path'].$domain.'-'.$locale.'.mo');
            // Print end
            $this->_print_success('MO:'.$locale.' file generated!');
            $this->_lineBreak();
        } catch (Exception $e) {
            error_log($e);
            throw $e;
        }
    }

    /**
     * Generate translations.
     * @since 1.1.0
     * 
     * @param string $locale PO locale.
     */
    protected function generateTranslations($locale)
    {
        try {
            $domain = $this->config['localize']['textdomain'];
            // Handle PO
            $this->generatePo($locale);
            $loader = new PoLoader;
            $po_filename = $this->config['localize']['path'].$domain.'-'.$locale.'.po';
            $translations = $loader->loadFile($po_filename);
            // Handle PO
            foreach ($translations as $translation) {
                $this->_print('------------------------------');
                $this->_lineBreak();
                $this->_print('-- Original text');
                $this->_lineBreak();
                if ($translation->getContext()) {
                    $this->_print('-- CONTEXT: '.Coloring::apply('color_42', $translation->getContext()));
                    $this->_lineBreak();
                }
                $this->_print(Coloring::apply('color_31', $translation->getOriginal()));
                $this->_lineBreak();
                $current_translation = $translation->getTranslation();
                $translate = true;
                $single_translate = null;
                if (!empty($current_translation)) {
                    $this->_print('-- Translated text');
                    $this->_lineBreak();
                    $this->_print(Coloring::apply('color_11', $current_translation));
                    $this->_lineBreak();
                    $this->_print('-- Do you want to change the existing translation '.$this->yesno().'?');
                    $this->_lineBreak();
                    $translate = $this->confirm();
                }
                if ($translate) {
                    $this->_print('-- Type the translation and press ENTER:');
                    $this->_lineBreak();
                    $translation->translate($this->listener->getInput());
                }
                if ($translation->getPlural()) {
                    $this->_print('-- Plural text');
                    $this->_lineBreak();
                    if ($translation->getContext()) {
                        $this->_print('-- CONTEXT: '.Coloring::apply('color_42', $translation->getContext()));
                        $this->_lineBreak();
                    }
                    $this->_print(Coloring::apply('color_31', $translation->getPlural()));
                    $this->_lineBreak();
                    $current_translation = implode(' ', $translation->getPluralTranslations());
                    $translate = true;
                    if (!empty($current_translation)) {
                        $this->_print('-- Translated text');
                        $this->_lineBreak();
                        $this->_print(Coloring::apply('color_11', $current_translation));
                        $this->_lineBreak();
                        $this->_print('-- Do you want to change the existing plural translation '.$this->yesno().'?');
                        $this->_lineBreak();
                        $translate = $this->confirm();
                    }
                    if ($translate) {
                        $this->_print('-- Type the plural translation and press ENTER:');
                        $this->_lineBreak();
                        $translation->translatePlural($this->listener->getInput());
                    }
                }
                $translations = $translations->add($translation);
            }
            $generator = new PoGenerator();
            $generator->generateFile($translations, $po_filename);
            // Print end
            if (count($translations) > 0) {
                $this->_print('------------------------------');
                $this->_lineBreak();
                $this->_print_success('Translations for PO:'.$locale.' have been generated!');
                $this->_lineBreak();
                $this->generateMo($locale);
            }
        } catch (Exception $e) {
            error_log($e);
            throw $e;
        }
    }
}