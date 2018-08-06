<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace FakeNamespace\FakeSubNamespace;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Exception\NotFoundException;

/**
 * @SuppressWarnings(PHPMD.AllPurposeAction)
 */
class FakeAction extends Action
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        throw new NotFoundException(__('I do not do anything'));
    }
}
