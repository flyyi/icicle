<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable;

use Icicle\Observable\Exception\{CompletedError, IncompleteError, UninitializedError};

class EmitterIterator implements ObservableIterator
{
    /**
     * @var \Icicle\Observable\Internal\Placeholder
     */
    private $placeholder;

    /**
     * @var mixed
     */
    private $current;

    /**
     * @var \Icicle\Observable\Internal\EmitQueue
     */
    private $queue;

    /**
     * @var \Icicle\Awaitable\Awaitable
     */
    private $awaitable;

    /**
     * @param \Icicle\Observable\Internal\EmitQueue $queue
     */
    public function __construct(Internal\EmitQueue $queue)
    {
        $this->queue = $queue;
        $this->queue->increment();
    }

    /**
     * Removes queue from collection.
     */
    public function __destruct()
    {
        if (null !== $this->placeholder) {
            $this->placeholder->ready();
        }

        $this->queue->decrement();
    }

    /**
     * {@inheritdoc}
     */
    public function wait(): \Generator
    {
        while (null !== $this->awaitable) {
            yield $this->awaitable; // Wait until last call has resolved.
        }

        if (null !== $this->placeholder) {
            $this->placeholder->ready();
        }

        try {
            $this->placeholder = $this->queue->pull();
            $this->current = yield $this->awaitable = $this->placeholder->getAwaitable();
        } catch (\Throwable $exception) {
            $this->current = $exception;
            throw $exception;
        } finally {
            $this->awaitable = null;
        }

        return !$this->queue->isComplete();
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrent()
    {
        if (null === $this->placeholder || null !== $this->awaitable) {
            throw new UninitializedError('wait() must be called before calling this method.');
        }

        if ($this->queue->isComplete()) {
            throw new CompletedError('The observable has completed and the iterator is invalid.');
        }

        return $this->current;
    }

    /**
     * {@inheritdoc}
     */
    public function getReturn()
    {
        if (null === $this->placeholder || null !== $this->awaitable) {
            throw new UninitializedError('wait() must be called before calling this method.');
        }

        if (!$this->queue->isComplete() || $this->queue->isFailed()) {
            throw new IncompleteError('The observable has not completed.');
        }

        return $this->current;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        return !$this->queue->isComplete();
    }
}
