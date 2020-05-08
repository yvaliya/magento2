<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Cms\Block;

use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * This class is replacement of \Magento\Cms\Block\Block, that accepts only `string` identifier of CMS Block
 *
 * @method getIdentifier(): int Returns the value of `identifier` injected in `<block>` definition
 */
class BlockByIdentifier extends AbstractBlock implements IdentityInterface
{
    const CACHE_KEY_PREFIX = 'CMS_BLOCK';

    /**
     * @var GetBlockByIdentifierInterface
     */
    private $blockByIdentifier;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var FilterProvider
     */
    private $filterProvider;

    /**
     * @var BlockInterface
     */
    private $cmsBlock;

    public function __construct(
        GetBlockByIdentifierInterface $blockByIdentifier,
        StoreManagerInterface $storeManager,
        FilterProvider $filterProvider,
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->blockByIdentifier = $blockByIdentifier;
        $this->storeManager = $storeManager;
        $this->filterProvider = $filterProvider;
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        try {
            return $this->filterOutput(
                $this->getCmsBlock()->getContent()
            );
        } catch (NoSuchEntityException $e) {
            return '';
        }
    }

    /**
     * Filters the Content
     *
     * @param string $content
     * @return string
     * @throws NoSuchEntityException
     */
    private function filterOutput(string $content): string
    {
        return $this->filterProvider->getBlockFilter()
            ->setStoreId($this->getCurrentStoreId())
            ->filter($content);
    }

    /**
     * Loads the CMS block by `identifier` provided as an argument
     *
     * @return BlockInterface
     * @throws NoSuchEntityException
     */
    private function getCmsBlock(): BlockInterface
    {
        if (!$this->getIdentifier()) {
            throw new NoSuchEntityException(
                __('Expected value of `identifier` was not provided')
            );
        }

        if (null === $this->cmsBlock) {
            $this->cmsBlock = $this->blockByIdentifier->execute(
                (string)$this->getIdentifier(),
                $this->getCurrentStoreId()
            );
        }

        return $this->cmsBlock;
    }

    /**
     * Returns the current Store ID
     *
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getCurrentStoreId(): int
    {
        return (int)$this->storeManager->getStore()->getId();
    }

    /**
     * Returns array of Block Identifiers used to determine Cache Tags
     *
     * This implementation supports different CMS blocks caching having the same identifier,
     * resolving the bug introduced in scope of \Magento\Cms\Block\Block
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        try {
            $cmsBlock = $this->getCmsBlock();

            $identities = [self::CACHE_KEY_PREFIX . '_' . $cmsBlock->getId()];

            if (method_exists($this->getCmsBlock(), 'getStores')) {
                foreach ($cmsBlock->getStores() as $store) {
                    $identities[] = self::CACHE_KEY_PREFIX . '_' . $this->getIdentifier() . '_' . $store;
                }
            }

            $identities[] = self::CACHE_KEY_PREFIX . '_' . $this->getIdentifier() . '_' . $this->getCurrentStoreId();

            return $identities;
        } catch (NoSuchEntityException $e) {
            // If CMS Block does not exist, it should not be cached
            return [];
        }
    }
}
