<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\SecurityBundle\Entity\Permission;
use Sulu\Bundle\SecurityBundle\Entity\Role;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The user an AI client acts as over MCP. It can view, add and edit products, but not publish them:
 * the MCP tools check these permissions exactly like the admin does.
 */
#[AsCommand(name: 'app:create-ai-editor', description: 'Creates the role "Product editor" and the user "ai-editor"')]
final class CreateAiEditorCommand extends Command
{
    private const ROLE = 'Product editor';

    // the bits of Sulu's permission mask
    private const VIEW = 64;
    private const ADD = 32;
    private const EDIT = 16;

    private const PERMISSIONS = [
        'sulu.product.products' => self::VIEW | self::ADD | self::EDIT,
        'sulu.product.attributes' => self::VIEW,
        'sulu.product.attribute_groups' => self::VIEW,
        'sulu.product.product_families' => self::VIEW,
        'sulu.media.collections' => self::VIEW,
        'sulu.webspaces.website' => self::VIEW,
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $role = new Role();
        $role->setName(self::ROLE);
        $role->setSystem('Sulu');

        foreach (self::PERMISSIONS as $context => $permissions) {
            $permission = new Permission();
            $permission->setRole($role);
            $permission->setContext($context);
            $permission->setPermissions($permissions);
            $role->addPermission($permission);
        }

        $this->entityManager->persist($role);
        $this->entityManager->flush();

        $this->getApplication()?->find('sulu:security:user:create')->run(new ArrayInput([
            'username' => 'ai-editor',
            'firstName' => 'AI',
            'lastName' => 'Editor',
            'email' => 'ai-editor@example.org',
            'locale' => 'en',
            'role' => self::ROLE,
            'password' => 'ai-editor',
        ]), $output);

        (new SymfonyStyle($input, $output))->success('User "ai-editor" (password "ai-editor") has the role "Product editor".');

        return Command::SUCCESS;
    }
}
