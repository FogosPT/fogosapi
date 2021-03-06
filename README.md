# Fogos.pt API


```
///////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////,*/////////////////////////////////
////////////////////////////////////////////*  *///////////////////////////////
////////////////////////////////////////////*   ,//////////////////////////////
////////////////////////////////////////////*    ,/////////////////////////////
////////////////////////////////////////////.     *////////////////////////////
///////////////////////////////////////////.      .////////////////////////////
/////////////////////////////////////////*         ////////////////////////////
///////////////////////////////////////*           ////////////////////////////
////////////////////////////////////*.             ////////////////////////////
/////////////////////////////////,                .////////////////////////////
//////////////////////////////*                   *//////*/////////////////////
////////////////////////////*                    ./////. */////////////////////
//////////////////////////*.                     *///,   */////////////////////
/////////////////////////*                      .///     */////////////////////
////////////////////////*                       *//.     ./////////////////////
///////////////////////*                        */*       ,////////////////////
///////////////////////.                        */*        *///////////////////
//////////////////////*                         ,/*         ,//////////////////
///////////////////* **                          */.         ./////////////////
/////////////////*   /*                           ,*           ////////////////
////////////////.   ./*                                         *//////////////
//////////////*      //*                                         */////////////
/////////////,        ,,.                                         *////////////
////////////,                                                      *///////////
///////////,                                                       .///////////
//////////*                                                         *//////////
//////////,                           ,.                            ,//////////
//////////,                          */*                            .//////////
//////////*                         *////.                          .//////////
///////////                        ,//////*                         *//////////
///////////*                       //////////,                      ///////////
////////////*       ,             ,////////////*                   *///////////
//////////////,     ,*            *//////////////.                *////////////
////////////////.    */,          ////////////////,             .//////////////
//////////////////*   ,////*,     /////////////////.          .*///////////////
/////////////////////*  *//////.  *////////////////*        ,//////////////////
////////////////////////*,*//////,./////////////////.    ,*////////////////////
///////////////////////////////////*////////////////,,*////////////////////////
```

## Run

```shell
$ cp docker-compose.override-example.yaml docker-compose.override.yaml
$ docker-compose up
```

### Running the Php-Cs-Fixer

On the entire project:
```shell
$ docker-compose exec fogos.api php vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist
```

On a specific file:
```shell
$ docker-compose exec fogos.api php vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist {file-path}
```

### TODO

- [ ] Ocorrencias Importantes (nao funcionam atualmente do lado da ANEPC)
- [ ] Suporte para GraphQL
- [ ] Novas funcionalidades via ICNF
- [ ] Tweets de perigo de incendio com bug (talvez bug nas threads)

## Slack

https://communityinviter.com/apps/fogospt/fogos-pt

# License

Copyright 2021

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language govern
