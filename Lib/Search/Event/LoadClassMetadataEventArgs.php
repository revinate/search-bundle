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
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Revinate\SearchBundle\Lib\Search\Event;

use Doctrine\Common\EventArgs;
use Doctrine\Common\Persistence\ObjectManager;
use Revinate\SearchBundle\Lib\Search\Mapping\ClassMetadata;
use Revinate\SearchBundle\Lib\Search\SearchManager;

/**
 * Class that holds event arguments for a loadMetadata event.
 *
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Mike Lohmann <mike.h.lohmann@googlemail.com>
 */
class LoadClassMetadataEventArgs extends EventArgs
{
    /**
     * @var SearchManager
     */
    private $sm;

    /**
     * @var ClassMetadata
     */
    private $classMetadata;

    /**
     * Constructor.
     *
     * @param ClassMetadata $classMetadata
     * @param SearchManager $sm
     */
    public function __construct(ClassMetadata $classMetadata, SearchManager $sm)
    {
        $this->classMetadata = $classMetadata;
        $this->sm = $sm;
    }

    /**
     * Retrieve associated ClassMetadata.
     *
     * @return ClassMetadata
     */
    public function getClassMetadata()
    {
        return $this->classMetadata;
    }

    /**
     * Retrieve associated SearchManager.
     *
     * @return SearchManager
     */
    public function getSearchManager()
    {
        return $this->sm;
    }

    /**
     * Retrieve associated SearchManager.
     *
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return $this->sm;
    }
}
