<?php

namespace esc\Classes;

/**
 * Class ManiaLinkDrag
 *
 * Helper script for dragging ManiaLinks (could need optimization).
 * Add {(new esc\Classes\ManiaLinkDrag())|noescape} to your ManiaScript, make sure you have a quad in your header area with the id "handle", if not set your quad-id in the constructor.
 * Call maniaLinkDrag(); in a loop.
 *
 * @package esc\Classes
 */
class ManiaLinkDrag
{
    public $targetId;

    /**
     * ManiaLinkDrag constructor.
     *
     * @param string $targetId
     */
    public function __construct($targetId = "handle")
    {
        $this->targetId = $targetId;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return '
Void maniaLinkDrag(){
    declare frame <=> (Page.MainFrame.GetFirstChild("' . $this->targetId . '") as CMlFrame);
    
    if(!frame.Parent.Visible){
        return;
    }
    
    declare framePos = frame.AbsolutePosition_V3;
    declare frameSize = frame.Size;
    
    if(MouseLeftButton){
        if(MouseX >= framePos[0]){
            if(MouseY <= framePos[1]){
                if(MouseX <= (framePos[0] + frameSize[0]) && MouseY >= (framePos[1] - frameSize[1])){
                    declare Real ZIndex for LocalUser = 305.0;
                    declare startPos = frame.Parent.RelativePosition_V3;
                    declare startX = MouseX;
                    declare startY = MouseY;
                    
                    if(frame.Parent.ZIndex > ZIndex){
                        ZIndex = frame.Parent.ZIndex;
                    }
                    
                    ZIndex = ZIndex + 1.0;
                    frame.Parent.ZIndex = ZIndex;
                    
                    while(MouseLeftButton){
                        yield;
                        
                        declare newPosX = startPos[0] + (MouseX - startX);
                        declare newPosY = startPos[1] + (MouseY - startY);
                        
                        frame.Parent.RelativePosition_V3 = <newPosX, newPosY>;
                    }
                }
            }
        }
    }
    
}';
    }
}
