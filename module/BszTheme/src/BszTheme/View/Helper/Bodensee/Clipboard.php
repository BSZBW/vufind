<?php
/*
 * Copyright 2020 (C) Bibliotheksservice-Zentrum Baden-
 * Württemberg, Konstanz, Germany
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

namespace BszTheme\View\Helper\Bodensee;

use Zend\View\Helper\AbstractHelper;

/**
 * Add a copy to clipboard icon to text content
 * @package  BszTheme\View\Helper\Bodensee
 * @category boss
 * @author   Cornelius Amzar <cornelius.amzar@bsz-bw.de>
 */
class Clipboard extends AbstractHelper
{
    /**
     * @param string $content content to be copied on click
     * @param string $id      ID of an already created element
     *
     * @return string
     */
    public function __invoke(string $content, string $id = '')
    {
        return $this->getView()->render('Helpers/clipboard.phtml', [
            'content' => $content,
            'id' => $id
        ]);
    }
}
