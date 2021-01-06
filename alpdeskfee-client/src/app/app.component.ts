import { Component, ComponentFactoryResolver, ComponentRef, ElementRef, HostListener, OnInit, ViewChild, ViewContainerRef } from '@angular/core';
import { DomSanitizer } from '@angular/platform-browser';
import { ItemContainerComponent } from './item-container/item-container.component';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss']
})
export class AppComponent implements OnInit {

  static ALPDESK_EVENTNAME = 'alpdesk_frontendediting_event'
  static ACTION_INIT = 'init';

  TARGETTYPE_PAGE = 'page';
  TARGETTYPE_ARTICLE = 'article';
  TARGETTYPE_CE = 'ce';
  TARGETTYPE_MOD = 'mod';

  @HostListener('document:' + AppComponent.ALPDESK_EVENTNAME, ['$event']) onAFEE_Event(event: CustomEvent) {
    if(event.detail.action === AppComponent.ACTION_INIT) {
      this.scanElements(event.detail.labels);
    }
  }

  @ViewChild('alpdeskfeeframe') alpdeskfeeframe!: ElementRef;

  title = 'alpdeskfee-client';  
  url: any;
  urlBase = 'https://contao.local:8890/preview.php';
  frameHeight = (window.innerHeight - 100) + 'px';
  frameWidth = '100%';
  frameLocation!: any;

  compRef!: ComponentRef<ItemContainerComponent>;

  constructor(private _sanitizer: DomSanitizer, private vcRef: ViewContainerRef, private resolver: ComponentFactoryResolver) { 
  }

  ngOnInit() {
    this.url = this._sanitizer.bypassSecurityTrustResourceUrl(this.urlBase);
  }


  iframeLoad() {
    this.frameLocation = this.alpdeskfeeframe.nativeElement.contentWindow.location.href;
  }

  scanElements(objLabels: any) {

    let data = this.alpdeskfeeframe.nativeElement.contentWindow.document.querySelectorAll("*[data-alpdeskfee]");
    for (let i = 0; i < data.length; i++) {
      let jsonData = data[i].getAttribute('data-alpdeskfee');
      if (jsonData !== null && jsonData !== undefined && jsonData !== '') {
        const obj = JSON.parse(jsonData);
        if (obj !== null && obj !== undefined) {
          if (obj.type === this.TARGETTYPE_ARTICLE) {
            let parentNode = data[i].parentElement;
            parentNode.classList.add('alpdeskfee-article-container');
            //appendUtilsContainer(obj, data[i], false, objLabels, true);
            /*parentNode.onmouseover = function () {
              data[i].classList.add("alpdeskfee-parent-active");
            };
            parentNode.onmouseout = function () {
              data[i].classList.remove("alpdeskfee-parent-active");
            };*/
          } else {
            data[i].classList.add('alpdeskfee-ce-container');
            /*appendUtilsContainer(obj, data[i], true, objLabels, true);
            data[i].onmouseover = function () {
              data[i].classList.add('alpdeskfee-active');
            };
            data[i].onmouseout = function () {
              data[i].classList.remove('alpdeskfee-active');
            };
            setContextMenu(data[i], 'alpdeskfee-active-force', '*[data-alpdeskfee]');*/
          }
        }
      }
    }
  }

}
