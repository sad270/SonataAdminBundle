<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminBundle\Util;

use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Security\Handler\AclSecurityHandlerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;

/**
 * @author Thomas Rabaix <thomas.rabaix@sonata-project.org>
 */
abstract class ObjectAclManipulator implements ObjectAclManipulatorInterface
{
    /**
     * Configure the object ACL for the passed object identities.
     *
     * @param AdminInterface<object>                $admin
     * @param \Traversable<ObjectIdentityInterface> $oids
     *
     * @throws \Exception
     *
     * @return array{int, int} [countAdded, countUpdated]
     *
     * @phpstan-template T of object
     * @phpstan-param AdminInterface<T> $admin
     */
    final public function configureAcls(
        OutputInterface $output,
        AdminInterface $admin,
        \Traversable $oids,
        ?UserSecurityIdentity $securityIdentity = null
    ): array {
        $countAdded = 0;
        $countUpdated = 0;
        $securityHandler = $admin->getSecurityHandler();
        if (!$securityHandler instanceof AclSecurityHandlerInterface) {
            $output->writeln(\sprintf('Admin `%s` is not configured to use ACL : <info>ignoring</info>', $admin->getCode()));

            return [0, 0];
        }

        $acls = $securityHandler->findObjectAcls($oids);

        foreach ($oids as $oid) {
            if ($acls->contains($oid)) {
                $acl = $acls->offsetGet($oid);
                ++$countUpdated;
            } else {
                $acl = $securityHandler->createAcl($oid);
                ++$countAdded;
            }

            if (null !== $securityIdentity) {
                // add object owner
                $securityHandler->addObjectOwner($acl, $securityIdentity);
            }

            $securityHandler->addObjectClassAces($acl, $admin);

            try {
                $securityHandler->updateAcl($acl);
            } catch (\Exception $e) {
                $output->writeln(\sprintf('Error saving ObjectIdentity (%s, %s) ACL : %s <info>ignoring</info>', $oid->getIdentifier(), $oid->getType(), $e->getMessage()));
            }
        }

        return [$countAdded, $countUpdated];
    }
}
