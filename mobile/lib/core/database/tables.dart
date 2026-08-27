import 'package:drift/drift.dart';

class LocalUsers extends Table {
  TextColumn get id => text()();
  TextColumn get name => text()();
  TextColumn get email => text()();
  BoolColumn get isPremiumCached => boolean().withDefault(const Constant(false))();
  TextColumn get timezone => text().withDefault(const Constant('Asia/Jakarta'))();

  @override
  Set<Column> get primaryKey => {id};
}

class LocalWallets extends Table {
  TextColumn get id => text()();
  TextColumn get userId => text()();
  TextColumn get name => text()();
  TextColumn get type => text().withDefault(const Constant('bank'))();
  TextColumn get currency => text().withDefault(const Constant('IDR'))();
  RealColumn get balance => real().withDefault(const Constant(0.0))();
  IntColumn get serverRevision => integer().withDefault(const Constant(1))();
  BoolColumn get isDeleted => boolean().withDefault(const Constant(false))();

  @override
  Set<Column> get primaryKey => {id};
}

class LocalCategories extends Table {
  TextColumn get id => text()();
  TextColumn get userId => text().nullable()();
  TextColumn get name => text()();
  TextColumn get type => text().withDefault(const Constant('expense'))();
  TextColumn get icon => text().nullable()();
  TextColumn get color => text().nullable()();
  TextColumn get parentId => text().nullable()();
  IntColumn get serverRevision => integer().withDefault(const Constant(1))();

  @override
  Set<Column> get primaryKey => {id};
}

class LocalTransactions extends Table {
  TextColumn get id => text()();
  TextColumn get userId => text()();
  TextColumn get walletId => text().nullable()();
  TextColumn get categoryId => text().nullable()();
  TextColumn get plannedExpenseId => text().nullable()();
  TextColumn get type => text()(); // expense, income, transfer
  RealColumn get amount => real()();
  TextColumn get currency => text().withDefault(const Constant('IDR'))();
  DateTimeColumn get transactionDate => dateTime()();
  TextColumn get description => text().nullable()();
  TextColumn get notes => text().nullable()();
  BoolColumn get isVoiceLogged => boolean().withDefault(const Constant(false))();
  BoolColumn get isExcludedFromStats => boolean().withDefault(const Constant(false))();
  IntColumn get serverRevision => integer().withDefault(const Constant(1))();
  BoolColumn get isDeleted => boolean().withDefault(const Constant(false))();

  @override
  Set<Column> get primaryKey => {id};
}

class LocalSubscriptions extends Table {
  TextColumn get id => text()();
  TextColumn get userId => text()();
  TextColumn get walletId => text().nullable()();
  TextColumn get categoryId => text().nullable()();
  TextColumn get name => text()();
  TextColumn get originalCurrency => text().withDefault(const Constant('IDR'))();
  RealColumn get originalAmount => real()();
  RealColumn get estimatedIdrAmount => real()();
  TextColumn get billingCycle => text().withDefault(const Constant('monthly'))();
  IntColumn get billingDay => integer().withDefault(const Constant(1))();
  DateTimeColumn get nextBillingDate => dateTime()();
  BoolColumn get remindH3 => boolean().withDefault(const Constant(true))();
  BoolColumn get remindH1 => boolean().withDefault(const Constant(true))();
  TextColumn get status => text().withDefault(const Constant('active'))();
  IntColumn get serverRevision => integer().withDefault(const Constant(1))();

  @override
  Set<Column> get primaryKey => {id};
}

class LocalPlannedExpenses extends Table {
  TextColumn get id => text()();
  TextColumn get userId => text()();
  TextColumn get subscriptionId => text()();
  TextColumn get walletId => text().nullable()();
  TextColumn get categoryId => text().nullable()();
  RealColumn get estimatedIdrAmount => real()();
  RealColumn get actualIdrAmount => real().nullable()();
  DateTimeColumn get dueDate => dateTime()();
  TextColumn get billingCycleKey => text()();
  TextColumn get status => text().withDefault(const Constant('pending'))(); // pending, confirmed, skipped
  DateTimeColumn get confirmedAt => dateTime().nullable()();
  IntColumn get serverRevision => integer().withDefault(const Constant(1))();

  @override
  Set<Column> get primaryKey => {id};
}

class LocalSyncQueue extends Table {
  TextColumn get id => text()(); // UUID of mutation
  TextColumn get entity => text()(); // wallets, transactions, subscriptions, etc.
  TextColumn get action => text()(); // create, update, delete
  IntColumn get baseRevision => integer().withDefault(const Constant(0))();
  TextColumn get payloadJson => text()();
  TextColumn get status => text().withDefault(const Constant('pending'))(); // pending, synced, failed_conflict, error
  IntColumn get retryCount => integer().withDefault(const Constant(0))();
  TextColumn get errorMessage => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();

  @override
  Set<Column> get primaryKey => {id};
}
