<?php

namespace esc\Classes;


class HideScript
{
    public $targetId;
    public $hideOnPodium;

    public function __construct($targetId = "widget", $hideOnPodium = false)
    {
        $this->targetId     = $targetId;
        $this->hideOnPodium = $hideOnPodium;
    }

    public function __toString()
    {
        return '
Void hidescript(){
    declare hideSpeed for LocalUser = 10;

    if(hideSpeed == -1 || InputPlayer == Null){
        return;
    }

    declare CMlFrame widget <=> (Page.MainFrame.GetFirstChild("' . $this->targetId . '") as CMlFrame);
    declare Boolean hidden = widget.DataAttributeGet("hidden") == "true";
    declare Real speed = ML::Abs(InputPlayer.Speed * 3.6);

    if(widget.DataAttributeGet("orig-x") == ""){
        widget.DataAttributeSet("orig-x", TL::ToText(widget.RelativePosition_V3[0]));
        widget.DataAttributeSet("orig-y", TL::ToText(widget.RelativePosition_V3[1]));

        declare Vec2 posHidden = widget.RelativePosition_V3;
        if(widget.RelativePosition_V3[0] < 0.0){
            posHidden[0] = posHidden[0] - widget.Size[0] * widget.RelativeScale - 2.0;
        }else{
            posHidden[0] = posHidden[0] + widget.Size[0] * widget.RelativeScale + 2.0;
        }

        widget.DataAttributeSet("hidden-x", TL::ToText(posHidden[0]));
        widget.DataAttributeSet("hidden-y", TL::ToText(posHidden[1]));
    }

    declare Text visiblePos = "<frame pos=\'" ^ widget.DataAttributeGet("orig-x") ^ " " ^ widget.DataAttributeGet("orig-y") ^ "\' />";
    declare Text hiddenPos = "<frame pos=\'" ^ widget.DataAttributeGet("hidden-x") ^ " " ^ widget.DataAttributeGet("hidden-y") ^ "\' />";
    
    declare Boolean playerIsRacing = InputPlayer.RaceState == CTmMlPlayer::ERaceState::Running;
    declare Boolean mapFinished = ' . ($this->hideOnPodium ? "UI.UISequence == CUIConfig::EUISequence::Podium" : "False") . ';
    declare Boolean overHidespeed = speed >= hideSpeed;
    
    if(mapFinished){
        if(!hidden){
            widget.DataAttributeSet("hidden", "true");
            AnimMgr.Add(widget, hiddenPos, 800, CAnimManager::EAnimManagerEasing::ExpInOut);
        }
    }else{
        if(overHidespeed && playerIsRacing && !hidden){
            widget.DataAttributeSet("hidden", "true");
            AnimMgr.Add(widget, hiddenPos, 800, CAnimManager::EAnimManagerEasing::ExpInOut);
        }
        if((!overHidespeed || !playerIsRacing) && hidden){
            widget.DataAttributeSet("hidden", "false");
            AnimMgr.Add(widget, visiblePos, 600, CAnimManager::EAnimManagerEasing::ExpInOut);
        }
    }

}';
    }
}