<?php

namespace esc\ManiaLink;


class Element
{
    public function getHeight()
    {
        if ($this instanceof Label) {
            return $this->getHeight();
        }

        return 0;
    }

    public function toString($offsetX, $offsetY, $padding): string
    {
        return "";
    }
}