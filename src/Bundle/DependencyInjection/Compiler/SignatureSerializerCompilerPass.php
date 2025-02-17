<?php

declare(strict_types=1);

namespace Jose\Bundle\JoseFramework\DependencyInjection\Compiler;

use Jose\Component\Signature\Serializer\JWSSerializerManagerFactory;
use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final readonly class SignatureSerializerCompilerPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasDefinition(JWSSerializerManagerFactory::class)) {
            return;
        }

        $definition = $container->getDefinition(JWSSerializerManagerFactory::class);

        $taggedAlgorithmServices = $container->findTaggedServiceIds('jose.jws.serializer');
        foreach ($taggedAlgorithmServices as $id => $tags) {
            $definition->addMethodCall('add', [new Reference($id)]);
        }
    }
}
