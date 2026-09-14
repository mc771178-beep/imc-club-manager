(function(){
  'use strict';
  function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
  document.querySelectorAll('[data-imccm-form]').forEach(function(form){
    form.addEventListener('submit',function(e){e.preventDefault();var btn=form.querySelector('button[type="submit"]'),result=form.querySelector('.imccm-result'),fd=new FormData(form);fd.append('nonce',IMCCM.nonce);btn.disabled=true;btn.textContent=IMCCM.messages.sending;result.textContent='';
      fetch(IMCCM.ajaxUrl,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}).then(function(j){result.className='imccm-result '+(j.success?'ok':'error');result.textContent=j.data&&j.data.message?j.data.message:IMCCM.messages.error;if(j.success)form.reset();}).catch(function(){result.className='imccm-result error';result.textContent=IMCCM.messages.error;}).finally(function(){btn.disabled=false;btn.textContent=form.dataset.imccmForm==='apply'?'送出申請':'送出報名';});
    });
  });
  document.querySelectorAll('.imccm-calendar').forEach(function(root){
    var events=[];try{events=JSON.parse(root.dataset.events||'[]');}catch(e){} var now=new Date(),cursor=new Date(now.getFullYear(),now.getMonth(),1),title=root.querySelector('[data-cal-title]'),days=root.querySelector('[data-cal-days]'),agenda=root.querySelector('[data-cal-agenda] div');
    function render(){var y=cursor.getFullYear(),m=cursor.getMonth(),first=new Date(y,m,1).getDay(),total=new Date(y,m+1,0).getDate();title.textContent=y+' 年 '+(m+1)+' 月';days.innerHTML='';for(var b=0;b<first;b++)days.insertAdjacentHTML('beforeend','<span class="blank"></span>');
      for(var d=1;d<=total;d++){var date=y+'-'+String(m+1).padStart(2,'0')+'-'+String(d).padStart(2,'0'),hits=events.filter(function(e){return(e.start||'').slice(0,10)===date;}),html='<span class="num">'+d+'</span>';hits.forEach(function(e){html+='<a href="'+esc(e.url)+'" title="'+esc(e.title)+'">'+esc(e.title)+'</a>';});days.insertAdjacentHTML('beforeend','<div class="day'+(hits.length?' has-event':'')+'">'+html+'</div>');}
      var monthEvents=events.filter(function(e){var dt=new Date(e.start);return dt.getFullYear()===y&&dt.getMonth()===m;});agenda.innerHTML=monthEvents.length?monthEvents.map(function(e){var dt=new Date(e.start);return '<article><time>'+String(dt.getDate()).padStart(2,'0')+'</time><div><a href="'+esc(e.url)+'">'+esc(e.title)+'</a><small>'+esc(e.venue||'地點待公布')+'</small></div></article>';}).join(''):'<p>本月目前沒有活動。</p>';
    }
    root.querySelector('[data-cal-prev]').onclick=function(){cursor.setMonth(cursor.getMonth()-1);render();};root.querySelector('[data-cal-next]').onclick=function(){cursor.setMonth(cursor.getMonth()+1);render();};render();
  });
})();
