<?php

namespace Acme\DemoBundle\Migrator\Phpcr;

use Doctrine\Bundle\PHPCRBundle\ManagerRegistry;
use Doctrine\Bundle\PHPCRBundle\Migrator\MigratorInterface;
use Doctrine\ODM\PHPCR\Document\AbstractFile;
use Doctrine\ODM\PHPCR\DocumentManager;
use Jackalope\Node;
use PHPCR\Query\QueryInterface;
use PHPCR\SessionInterface;
use PHPCR\Util\PathHelper;
use Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\AbstractMedia;
use Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\Directory;
use Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\Image;
use Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\Media;
use Symfony\Cmf\Bundle\MediaBundle\MediaInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * related to https://github.com/symfony-cmf/MediaBundle/pull/63
 */
class MediaNodeType implements MigratorInterface
{
    /** @var SessionInterface */
    protected $session;

    /** @var OutputInterface */
    protected $output;

    /** @var string */
    protected $tmpPostfix = '.bak';

    /** @var array */
    protected $newDirs = array();

    public function init(SessionInterface $session, OutputInterface $output)
    {
        $this->session = $session;
        $this->output = $output;
    }

    /**
     * @param string $identifier
     * @param int $depth
     * @return int exit code
     */
    public function migrate($identifier = '/', $depth = -1)
    {
        $qm = $this->session->getWorkspace()->getQueryManager();
        $removeNodes = array();

        // directory
        $query = $qm->createQuery('select * from [nt:unstructured]
            where [phpcr:class] = "Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\Directory"',
            QueryInterface::JCR_SQL2);

        // keep new directories in memory
        $newDirs = array();
        foreach ($query->execute() as $row) {
            /** @var \Jackalope\Node $node */
            $node = $row->getNode();

            $path = $node->getPath();
            $this->session->move($path, $path.$this->tmpPostfix);

            $dir = $this->createDirectory($node);

            $this->session->save();

            $this->newDirs[$dir->getPath()] = $dir;
            $removeNodes[] = $node;
        }

        $this->output->writeln(sprintf('<info>Migrating %s directory object(s).</info>', count($this->newDirs)));

        // media
        $query = $qm->createQuery('select * from [nt:unstructured]
            where [phpcr:class] = "Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\Media"',
            QueryInterface::JCR_SQL2);

        // keep new media in memory
        $newMedias = array();
        foreach ($query->execute() as $row) {
            /** @var \Jackalope\Node $node */
            $node = $row->getNode();

            $path = $node->getPath();
            $this->session->move($path, $path.$this->tmpPostfix);

            $media = $this->createMedia($node);

            $this->session->save();

            $newMedias[] = $media;
            $removeNodes[] = $node;
        }

        $this->output->writeln(sprintf('<info>Migrating %s media object(s).</info>', count($newMedias)));

        // file
        $query = $qm->createQuery('select * from [nt:unstructured]
            where [phpcr:class] = "Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\File"',
            QueryInterface::JCR_SQL2);

        // keep new files in memory
        $newFiles = array();
        foreach ($query->execute() as $row) {
            /** @var \Jackalope\Node $node */
            $node = $row->getNode();

            $path = $node->getPath();
            $this->session->move($path, $path.$this->tmpPostfix);

            $file = $this->createFile($node);

            $this->session->save();

            $newFiles[] = $file;
            $removeNodes[] = $node;
        }

        $this->output->writeln(sprintf('<info>Migrating %s file object(s)</info>', count($newFiles)));

        // image
        $query = $qm->createQuery('select * from [nt:unstructured]
            where [phpcr:class] = "Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\Image"',
            QueryInterface::JCR_SQL2);

        // keep new images in memory
        $newImages = array();
        foreach ($query->execute() as $row) {
            /** @var \Jackalope\Node $node */
            $node = $row->getNode();

            $path = $node->getPath();
            $this->session->move($path, $path.$this->tmpPostfix);

            $image = $this->createImage($node);

            $this->session->save();

            $newImages[] = $image;
            $removeNodes[] = $node;
        }

        $this->output->writeln(sprintf('<info>Migrating %s image object(s).</info>', count($newImages)));

        // remove old
        foreach ($removeNodes as $node) {
            if (!$node->isDeleted()) {
                $node->remove();
            }
        }

        $this->session->save();

        return 0;
    }

    protected function getNodeParent(Node $node)
    {
        $path = str_replace($this->tmpPostfix, '', $node->getPath());
        $path = PathHelper::getParentPath($path);

        return isset($this->newDirs[$path]) ? $this->newDirs[$path] : $this->session->getNode($path);
    }

    protected function getNodePath(Node $node)
    {
        return substr($node->getPath(), 0, -1 * strlen($this->tmpPostfix));
    }

    protected function createDirectory(Node $oldNode)
    {
        $parent = $this->getNodeParent($oldNode);
        $path = $this->getNodePath($oldNode);
        $newNode = $parent->addNode(PathHelper::getNodeName($path), 'nt:folder');

        // mixins
        $newNode->addMixin('phpcr:managed');
        $newNode->addMixin('mix:referenceable');
        $newNode->addMixin('mix:lastModified');

        // properties - phpcr
        $newNode->setProperty('phpcr:class', 'Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\Directory');
        $newNode->setProperty('phpcr:classparents', array(
                'Doctrine\ODM\PHPCR\Document\AbstractFile',
                'Doctrine\ODM\PHPCR\Document\Folder',
            ));

        // properties - mixins
        $newNode->setProperty('jcr:lastModified', $oldNode->getPropertyValue('jcr:lastModified'));
        $newNode->setProperty('jcr:lastModifiedBy', $oldNode->getPropertyValue('jcr:lastModifiedBy'));

        // properties - model

        return $newNode;
    }

    protected function setMediaProperties(Node $oldNode, Node $newNode)
    {
        if ($oldNode->hasProperty('description')) {
            $newNode->setProperty('description', $oldNode->getPropertyValue('description'));
        }
        if ($oldNode->hasProperty('copyright')) {
            $newNode->setProperty('copyright', $oldNode->getPropertyValue('copyright'));
        }
        if ($oldNode->hasProperty('authorName')) {
            $newNode->setProperty('authorName', $oldNode->getPropertyValue('authorName'));
        }
        if ($oldNode->hasProperty('metadata')) {
            $newNode->setProperty('metadata', $oldNode->getPropertyValue('metadata'));
        }
    }

    protected function createMedia(Node $oldNode)
    {
        $parent = $oldNode->getParent();
        $path = $this->getNodePath($oldNode);
        $newNode = $parent->addNode(PathHelper::getNodeName($path), 'cmf:mediaNode');

        // mixins
        $newNode->addMixin('phpcr:managed');
        $newNode->addMixin('mix:referenceable');
        $newNode->addMixin('mix:created');
        $newNode->addMixin('mix:lastModified');

        // properties - phpcr
        $newNode->setProperty('phpcr:class', 'Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\Media');
        $newNode->setProperty('phpcr:classparents', array(
                'Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\AbstractMedia',
            ));

        // properties - mixins
        $newNode->setProperty('jcr:lastModified', $oldNode->getPropertyValue('jcr:lastModified'));
        $newNode->setProperty('jcr:lastModifiedBy', $oldNode->getPropertyValue('jcr:lastModifiedBy'));

        // properties - model
        $this->setMediaProperties($oldNode, $newNode);

        return $newNode;
    }

    protected function createFile(Node $oldNode)
    {
        $parent = $oldNode->getParent();
        $path = $this->getNodePath($oldNode);
        $newNode = $parent->addNode(PathHelper::getNodeName($path), 'nt:file');

        // mixins
        $newNode->addMixin('phpcr:managed');
        $newNode->addMixin('mix:referenceable');
        $newNode->addMixin('cmf:media');

        // properties - phpcr
        $newNode->setProperty('phpcr:class', 'Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\File');
        $newNode->setProperty('phpcr:classparents', array(
                'Doctrine\ODM\PHPCR\Document\AbstractFile',
                'Doctrine\ODM\PHPCR\Document\File',
            ));

        // properties - model
        $this->setMediaProperties($oldNode, $newNode);

        $this->moveContent($oldNode, $newNode);

        return $newNode;
    }

    protected function createImage(Node $oldNode)
    {
        $newNode = $this->createFile($oldNode);

        $newNode->removeMixin('cmf:media');
        $newNode->addMixin('cmf:image');

        // properties - phpcr
        $newNode->setProperty('phpcr:class', 'Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\Image');
        $newNode->setProperty('phpcr:classparents', array(
                'Doctrine\ODM\PHPCR\Document\AbstractFile',
                'Doctrine\ODM\PHPCR\Document\File',
                'Symfony\Cmf\Bundle\MediaBundle\Doctrine\Phpcr\File'
            ));

        // properties - model
        $newNode->setProperty('width', $oldNode->getPropertyValue('width'));
        $newNode->setProperty('height', $oldNode->getPropertyValue('height'));

        return $newNode;
    }

    protected function moveContent(Node $oldNode, Node $newNode)
    {
        $this->session->move($oldNode->getPath().'/jcr:content', $newNode->getPath().'/jcr:content');
    }
}