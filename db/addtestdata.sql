use merx;

insert into DealerCredentials values( null, '0d3d6381-0e02-11e5-9eb5-20c9d0478db9', null, now(), now(), now(), null, 1,'12345');
insert into AuthorizedBSVKeys values( null, '108b6a78-4027-447b-9b2d-a6c9b7da72dc', 0 );
alter table PurchaseOrders auto_increment=1000;
insert into Vendors values( null, 'Wester Power Sports' );
insert into Warehouses values( null, 'California', 'CA' );
insert into Warehouses values( null, 'Idaho', 'ID' );
insert into Warehouses values( null, 'Pennsylvania', 'PA' );
insert into Warehouses values( null, 'Tennessee', 'TN' );

insert into Items values( null, 1, '53-04855', 'Rear Wheel Slide Rail Ext', 'MM-E8-55', 'MM',0,1,0,0,'83.38','159.95',0,'Street',0);
insert into ItemCost values( 1, 1, 83.38 );
insert into ItemStock values( 1, 1, 3 );
insert into ItemStock values( 1, 2, 5 );
insert into ItemStock values( 1, 3, 7 );
insert into ItemStock values( 1, 4, 2 );

insert into Items values( null, 1, '550-0138', 'HiFloFiltro Oil Filter', 'HF138', 'HiFlo',0,0,0,0,'4.49','7.95',0,'Street',0);
insert into ItemStock values( 2, 1, 1 );
insert into ItemStock values( 2, 2, 3 );
insert into ItemStock values( 2, 3, 6 );
insert into ItemStock values( 2, 4, 8 );

insert into Items values( null, 1, '730003', 'K&N Air Filter', 'HA-0003', 'K&N',0,0,0,0,'64.99','98.68',0,'Street',0);
insert into ItemCost values( 3, 1, 51.99 );
insert into ItemStock values( 3, 1, 3 );
insert into ItemStock values( 3, 2, 5 );
insert into ItemStock values( 3, 3, 22 );
insert into ItemStock values( 3, 4, 13 );

insert into Items values( null, 1, '2-B10HS', 'NGK Spark Plug #2399/10', '2399', 'NGK',0,0,0,0,'1.79','2.95',0,'Street',0);
insert into ItemCost values( 4, 1, 1.61 );
insert into ItemStock values( 4, 1, 33 );
insert into ItemStock values( 4, 2, 55 );
insert into ItemStock values( 4, 3, 2 );
insert into ItemStock values( 4, 4, 1 );

insert into Items values( null, 1, '87-9937', 'Michelin Tire 120/70 ZR18 Pilot RD4 GT', '49243', 'Michelin',0,0,1,0,'174.99','250.95',0,'Street',0);
insert into ItemCost values( 5, 1, 127.74 );
insert into ItemStock values( 5, 1, 3 );
insert into ItemStock values( 5, 2, 55 );
insert into ItemStock values( 5, 3, 24 );
insert into ItemStock values( 5, 4, 10 );

insert into UnitModel values( null, 1, '', 'CBR1000','','CBR1000',1,'2015',0,0,'',12000,14000,0,'');
insert into UnitModelCost values( 1,1,11500);
insert into UnitVehicleTypes values( null, 'Street' );
insert into UnitModelStock values( 1, 1, 4 );
insert into UnitModelStock values( 1, 2, 2 );
insert into UnitModelStock values( 1, 3, 1 );

insert into ItemImages values( null, 3, 'www.nizex.com',1);
insert into ItemImages values( null, 3, 'www.nizex.com/test',2);
insert into DaysToFullfill values(1,1,2);
insert into DaysToFullfill values(1,2,5);
insert into DaysToFullfill values(1,3,4);
insert into DaysToFullfill values(1,4,3);
