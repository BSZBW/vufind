<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ProxyManager\Factory;

use ProxyManager\Configuration;
use ProxyManager\Factory\RemoteObject\AdapterInterface;
use ProxyManager\ProxyGenerator\RemoteObjectGenerator;

/**
 * Factory responsible of producing remote proxy objects
 *
 * @author Vincent Blanchon <blanchon.vincent@gmail.com>
 * @license MIT
 */
class RemoteObjectFactory extends AbstractBaseFactory
{
    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * {@inheritDoc}
     *
     * @param AdapterInterface $adapter
     * @param Configuration    $configuration
     */
    public function __construct(AdapterInterface $adapter, Configuration $configuration = null)
    {
        parent::__construct($configuration);

        $this->adapter = $adapter;
    }

    /**
     * @param string|object $instanceOrClassName
     *
     * @return \ProxyManager\Proxy\RemoteObjectInterface
     */
    public function createProxy($instanceOrClassName)
    {
        $className      = is_object($instanceOrClassName) ? get_class($instanceOrClassName) : $instanceOrClassName;
        $proxyClassName = $this->generateProxy($className);

        return new $proxyClassName($this->adapter);
    }

    /**
     * {@inheritDoc}
     */
    protected function getGenerator()
    {
        return $this->generator ?: $this->generator = new RemoteObjectGenerator();
    }
}
