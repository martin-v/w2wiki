
function toggleDrawer()
{
     document.getElementById('drawer').classList.toggle('inactive');
}

// based on https://www.w3schools.com/howto/howto_js_draggable.asp
function makeElementDraggable(elmnt)
{
    var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
/*
    if (document.getElementById(elmnt.id + "header"))
    {
        // if present, the header is where you move the DIV from:
        document.getElementById(elmnt.id + "header").onmousedown = dragMouseDown;
    }
    else
    {
        // otherwise, move the DIV from anywhere inside the DIV:
*/
    elmnt.onmousedown = dragMouseDown;
//    }
  
    function dragMouseDown(e)
    {
        e = e || window.event;
        e.preventDefault();
        // get the mouse cursor position at startup:
        pos3 = e.clientX;
        pos4 = e.clientY;
        console.log("Click: "+pos3+", "+pos4);
        document.onmouseup = closeDragElement;
        // call a function whenever the cursor moves:
        document.onmousemove = elementDrag;
    }
  
    function elementDrag(e)
    {
        e = e || window.event;
        e.preventDefault();
        // calculate the new cursor position:
        pos1 = pos3 - e.clientX;
        pos2 = pos4 - e.clientY;
        console.log("Pos: "+e.clientX+", "+e.clientY+", drag: "+pos1+", "+pos2+", offsetLeftTop: "+elmnt.offsetLeft+", "+elmnt.offsetTop+", newPos: "+(elmnt.offsetLeft-pos1)+", "+(elmnt.offsetTop-pos2));
        pos3 = e.clientX;
        pos4 = e.clientY;
        // set the element's new position:
        elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
        elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
    }
  
    function closeDragElement()
    {
       // stop moving when mouse button is released:
       document.onmouseup = null;
       document.onmousemove = null;
    }
}

window.onload = function(e)
{
    makeElementDraggable(document.getElementById("drawer"));
};
