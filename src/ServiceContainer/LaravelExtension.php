<?php

namespace Laracasts\Behat\ServiceContainer;

use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\Testwork\EventDispatcher\ServiceContainer\EventDispatcherExtension;
use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class LaravelExtension implements Extension
{

    /**
     * {@inheritdoc}
     */
    public function getConfigKey()
    {
        return 'laravel';
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager)
    {
        if (null !== $minkExtension = $extensionManager->getExtension('mink')) {
            $minkExtension->registerDriverFactory(new LaravelFactory);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder
            ->children()
                ->scalarNode('bootstrap_path')
                    ->defaultValue('bootstrap/app.php')
                ->end()
                ->scalarNode('env_path')
                    ->defaultValue('.env.behat');
    }

    /**
     * {@inheritdoc}
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $app = $this->loadApplication($container, $config);
        $this->loadInitializer($container, $app);
    }

    /**
     * Boot up Laravel.
     *
     * @param  ContainerBuilder $container
     * @param  array            $config
     * @return HttpKernelInterface
     */
    private function loadApplication(ContainerBuilder $container, array $config)
    {
        $app = $this->requireLaravelBootstrap($container, $config);

        $app->loadEnvironmentFrom($config['env_path']);

        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

        $container->set('laravel.app', $app);

        return $app;
    }

    /**
     * Require Laravel's bootstrap file.
     *
     * @param ContainerBuilder $container
     * @param array            $config
     * @return mixed
     * @throws RuntimeException
     */
    private function requireLaravelBootstrap(ContainerBuilder $container, array $config)
    {
        $bootstrapPath = $container->getParameter('paths.base') . '/' . $config['bootstrap_path'];

        $this->guardAgainstMissingBootstrapPath($bootstrapPath);

        return require $bootstrapPath;
    }

    /**
     * Ensure that the provided Laravel bootstrap path exists.
     *
     * @param string $path
     * @throws RuntimeException
     */
    private function guardAgainstMissingBootstrapPath($path)
    {
        if ( ! file_exists($path)) {
            throw new RuntimeException('Could not locate the path to the Laravel bootstrap file.');
        }
    }

    /**
     * Load the initializer.
     *
     * @param ContainerBuilder    $container
     * @param HttpKernelInterface $app
     */
    private function loadInitializer(ContainerBuilder $container, $app)
    {
        $definition = new Definition('Laracasts\Behat\Context\KernelAwareInitializer', [$app]);

        $definition->addTag(EventDispatcherExtension::SUBSCRIBER_TAG, ['priority' => 0]);
        $definition->addTag(ContextExtension::INITIALIZER_TAG, ['priority' => 0]);

        $container->setDefinition('laravel.initializer', $definition);
    }

}