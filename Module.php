<?php declare(strict_types=1);
namespace IiifSearch;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;

use Laminas\Mvc\Controller\AbstractController;
use Laminas\View\Renderer\PhpRenderer;

use IiifSearch\Form\ConfigForm;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * getConfigForm
     *
     * 設定フォーム
     * @param  mixed $renderer
     */
    public function getConfigForm(PhpRenderer $renderer)
    {
        $translate = $renderer->plugin('translate');

        $services = $this->getServiceLocator();
        // 設定内容取得
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $data = [
            'iiifsearch_url' => $settings->get('iiifsearch_url', ''),
        ];
        $form->init();
        // フォームにデータを設定する
        $form->setData($data);
        $html = $renderer->formCollection($form);
        return $html;
    }

    /**
     * handleConfigForm
     *
     * 設定フォーム送信時
     * @param  mixed $controller
     */
    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $params = $controller->getRequest()->getPost();

        // 設定データ反映
        $settings->set('iiifsearch_url', $params["iiifsearch_url"]);
    }

    /*
    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(null, 'IiifSearch\Controller\Search');
    }
    */

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            '*',
            'iiifserver.manifest',
            [$this, 'handleIiifServerManifest']
        );
    }

    public function handleIiifServerManifest(Event $event): void
    {
        $type = $event->getParam('type');
        if ($type !== 'item') {
            return;
        }

        $services = $this->getServiceLocator();
        // 設定内容取得
        $settings = $services->get('Omeka\Settings');
        $iiifsearch_property = $settings->get('iiifsearch_url', '');

        $resource = $event->getParam('resource');        

        $searchServiceAvailable = false;
        $url = "";
        foreach ($resource->value($iiifsearch_property, ['all' => true]) as $value) {
            if ($value->type() === 'uri') {
                $url = $value->uri();
                $searchServiceAvailable = true;
                break;
            }
        }

        if (!$searchServiceAvailable) {
            return;
        }

        /** @var \IiifServer\Iiif\Manifest $manifest */
        $manifest = $event->getParam('manifest');

        // Manage last or recent version of module Iiif Server.
        $isVersion2 = !is_object($manifest);

        if ($isVersion2) {
            $manifest['service'][] = [
                '@context' => 'http://iiif.io/api/search/0/context.json',
                '@id' => $url,
                'profile' => 'http://iiif.io/api/search/0/search',
                'label' => 'Search within this manifest', // @translate
            ];
        } else {
            $manifest
                // Use of "@" is slightly more compatible with old viewers.
                ->appendService(new \IiifServer\Iiif\Service($resource, [
                    '@context' => 'http://iiif.io/api/search/0/context.json',
                    '@id' => $url,
                    '@type' => 'SearchService1',
                    'profile' => 'http://iiif.io/api/search/0/search',
                    'label' => 'Search within this manifest', // @translate
                ]))
            ;
        }

        $event->setParam('manifest', $manifest);
    }

    /**
     * install
     * インストールで実行する処理
     *
     * @param ServiceLocatorInterface $services
     */
    public function install(ServiceLocatorInterface $services): void
    {
        $translator = $services->get('MvcTranslator');
        // サービスをメンバー変数に設定する
        $this->setServiceLocator($services);
        // 依存モジュールチェック
        if (!$this->checkDependencies()) {
            $message = new Message(
                $translator->translate('This module requires modules "%s".'), // @translate
                implode('", "', $this->dependencies)
            );
            throw new ModuleCannotInstallException((string) $message);
        }
        // 後処理を実行する
        $this->postInstall($services);
    }

    /**
     * 依存モジュールチェック
     *
     * @return bool
     */
    protected function checkDependencies(): bool
    {
        // モジュール設定取得
        $config = $this->getConfig();
        // 依存モジュール取得
        $this->dependencies = $config['dependencies'];
        // 依存モジュールが存在しない、または全てアクティブの場合はtrue
        return empty($this->dependencies) || $this->areModulesActive($this->dependencies);
    }

    /**
     * areModulesActive
     *
     * 依存モジュールがアクティブかどうかチェック
     * @param array $modules
     * @return bool
     */
    protected function areModulesActive(array $modules): bool
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        foreach ($modules as $module) {
            $module = $moduleManager->getModule($module);
            // アクティブでない場合はfalse
            if (!$module || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
                return false;
            }
        }
        return true;
    }

    /**
     * postInstall
     *
     * インストール後処理
     * @param  mixed $services
     */
    protected function postInstall(ServiceLocatorInterface $services): void
    {
        // 設定追加
        $this->manageSetting('install');
    }

    /**
     * unistall
     * アンインストールで実行する処理
     *
     * @param ServiceLocatorInterface $services
     */
    public function uninstall(ServiceLocatorInterface $services): void
    {
        // 設定を削除する
        $this->manageSetting('unistall');
    }

    /**
     * manageSetting
     *
     * 設定を追加、削除する
     * @param [type] $type
     */
    private function manageSetting($type): void
    {
        // サービス取得
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        
        switch ($type) {
            // インストール時の追加処理
            case 'install':
                // 設定
                $settings->set('iiifsearch_url', "");
                break;
            case 'unistall':
                // 設定削除
                $settings->delete('iiifsearch_url');
                break;
        }
    }
}
