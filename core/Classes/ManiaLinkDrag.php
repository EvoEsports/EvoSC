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
    public string $targetId;

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
    declare Vec2[Text] lastFramePosition for This;
    declare handle <=> (Page.MainFrame.GetFirstChild("' . $this->targetId . '") as CMlFrame);
    
    if(!handle.Parent.Visible){
        return;
    }
    
    declare Text handleId = handle.DataAttributeGet("id");
    
    if(handleId == ""){
        handleId = "" ^ handle.Id;
    }
    
    declare framePos = handle.AbsolutePosition_V3;
    declare frameSize = handle.Size;
    
    if(handle.Parent.DataAttributeGet("centered") != "centered"){
        if(lastFramePosition.existskey(handleId)){
            handle.Parent.RelativePosition_V3 = lastFramePosition[handleId];
        }else{
            handle.Parent.RelativePosition_V3 = <handle.Parent.Size[0]/-2.0, handle.Parent.Size[1]/2.0>;
        }
        
        if(handle.Parent.RelativePosition_V3[1] > 150){
            handle.Parent.RelativePosition_V3 = <handle.Parent.RelativePosition_V3[0], handle.Parent.RelativePosition_V3[1] - 10>;
        }
        if(handle.Parent.RelativePosition_V3[1] < -150){
            handle.Parent.RelativePosition_V3 = <handle.Parent.RelativePosition_V3[0], handle.Parent.RelativePosition_V3[1] + 10>;
        }
    
        handle.Parent.DataAttributeSet("centered", "centered");
    }
    
    if(MouseLeftButton){
        if(MouseX >= framePos[0]){
            if(MouseY <= framePos[1]){
                if(MouseX <= (framePos[0] + frameSize[0]) && MouseY >= (framePos[1] - frameSize[1])){
                    declare Real ZIndex for LocalUser = 305.0;
                    declare startPos = handle.Parent.RelativePosition_V3;
                    declare startX = MouseX;
                    declare startY = MouseY;
                    
                    if(handle.Parent.ZIndex > ZIndex){
                        ZIndex = handle.Parent.ZIndex;
                    }
                    
                    ZIndex = ZIndex + 1.0;
                    handle.Parent.ZIndex = ZIndex;
                    
                    while(MouseLeftButton){
                        yield;
                        
                        declare newPosX = startPos[0] + (MouseX - startX);
                        declare newPosY = startPos[1] + (MouseY - startY);
                        
                        handle.Parent.RelativePosition_V3 = <newPosX, newPosY>;
                    }
                    
                    lastFramePosition[handleId] = handle.Parent.RelativePosition_V3;
                }
            }
        }
    }
    
}';
    }
}
