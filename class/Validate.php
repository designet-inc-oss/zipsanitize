<?php
/*
 * SaMMA zipsanitize plugin
 *
 * Copyright (C) 2017 DesigNET, INC.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

Class  Validate {
    public function Path($value)
    {
        return True;
    }

    public function Url($value)
    {
        return True;
    }

    public function Facility($value)
    {
        return True;
    }

    public function Exist($value)
    {
        return True;
    }

    public function Action($value)
    {
        return True;
    }
}
