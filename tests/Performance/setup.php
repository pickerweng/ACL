<?php
/**
 * Setup the performance tests.
 */

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use MyCLabs\ACL\ACL;
use MyCLabs\ACL\Doctrine\ACLSetup;
use Tests\MyCLabs\ACL\Performance\Model\Article;
use Tests\MyCLabs\ACL\Performance\Model\Category;
use Tests\MyCLabs\ACL\Performance\Model\User;

require_once __DIR__ . '/../../vendor/autoload.php';

// Create the entity manager
$paths = [
    __DIR__ . '/../../src/Model',
    __DIR__ . '/Model',
];
$dbParams = [
    'driver' => 'pdo_sqlite',
    'memory' => true,
];
$config = Setup::createAnnotationMetadataConfiguration($paths, true, null, new ArrayCache(), false);
$em = EntityManager::create($dbParams, $config);

// Create the ACL object
$acl = new ACL($em);

$setup = new ACLSetup();
$setup->setSecurityIdentityClass('Tests\MyCLabs\ACL\Performance\Model\User');
$setup->registerRoleClass('Tests\MyCLabs\ACL\Performance\Model\ArticleEditorRole', 'articleEditor');
$setup->registerRoleClass('Tests\MyCLabs\ACL\Performance\Model\AllArticlesEditorRole', 'allArticlesEditor');
$setup->registerRoleClass('Tests\MyCLabs\ACL\Performance\Model\CategoryManagerRole', 'categoryManager');
$setup->setUpEntityManager($em, function () use ($acl) {
    return $acl;
});

// Create the schema
$tool = new SchemaTool($em);
$tool->createSchema($em->getMetadataFactory()->getAllMetadata());
// Necessary so that SQLite supports CASCADE DELETE
if ($dbParams['driver'] == 'pdo_sqlite') {
    $em->getConnection()->executeQuery('PRAGMA foreign_keys = ON');
}


$users = [];
for ($i = 0; $i < 20; $i++) {
    $users[$i] = new User();
    $em->persist($users[$i]);
}

$categories = [];
$articles = [];
for ($i = 0; $i < 20; $i++) {
    $category = new Category();
    $em->persist($category);
    $categories[$i] = $category;

    for ($j = 0; $j < 100; $j++) {
        $article = new Article($category);
        $category->addArticle($article);
        $em->persist($article);
        $articles[$i * $j] = $article;
    }
}

$em->flush();
