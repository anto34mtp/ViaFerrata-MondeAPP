import React, {useState, useCallback} from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet,
  Alert, RefreshControl,
} from 'react-native';
import {useFocusEffect, useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp} from '@react-navigation/native-stack';
import {getTrips, deleteTrip, Trip} from '../api/client';
import {useLang} from '../context/LangContext';
import {useAuth} from '../context/AuthContext';
import LoadingScreen from '../components/LoadingScreen';
import {RootStackParamList} from '../navigation/AppNavigator';

const PRIMARY = '#2E7D32';
type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function RoadTripsScreen() {
  const {t} = useLang();
  const {user} = useAuth();
  const navigation = useNavigation<Nav>();
  const [myTrips, setMyTrips] = useState<Trip[]>([]);
  const [sharedTrips, setSharedTrips] = useState<Trip[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async (silent = false) => {
    if (!silent) setLoading(true);
    try {
      const res = await getTrips();
      const d = (res.data as any)?.data ?? res.data;
      setMyTrips(d?.my_trips ?? []);
      setSharedTrips(d?.shared_trips ?? []);
    } catch {
      // ignore
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  if (!user) {
    return (
      <View style={styles.centered}>
        <Text style={styles.loginMsg}>{t('auth.loginRequired')}</Text>
        <TouchableOpacity style={styles.loginBtn} onPress={() => navigation.navigate('Login')}>
          <Text style={styles.loginBtnText}>{t('nav.login')}</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (loading) return <LoadingScreen />;

  const handleDelete = (id: number, name: string) => {
    Alert.alert(t('trips.delete'), `${t('trips.confirmDelete')} "${name}" ?`, [
      {text: t('common.cancel'), style: 'cancel'},
      {
        text: t('common.delete'), style: 'destructive',
        onPress: async () => {
          try {
            await deleteTrip(id);
            load(true);
          } catch {
            Alert.alert(t('common.error'));
          }
        },
      },
    ]);
  };

  const TripCard = ({item, isShared}: {item: Trip; isShared?: boolean}) => (
    <TouchableOpacity style={styles.card} onPress={() => navigation.navigate('RoadTripDetail', {id: item.id})}>
      <View style={styles.cardHeader}>
        <View style={styles.cardTitleRow}>
          <Text style={styles.cardTitle}>{item.name}</Text>
          {isShared && <View style={styles.sharedBadge}><Text style={styles.sharedBadgeText}>Partagé</Text></View>}
        </View>
        {!isShared && (
          <TouchableOpacity onPress={() => handleDelete(item.id, item.name)}>
            <Text style={styles.deleteBtn}>🗑</Text>
          </TouchableOpacity>
        )}
      </View>
      {item.description ? <Text style={styles.desc} numberOfLines={2}>{item.description}</Text> : null}
      <View style={styles.cardMeta}>
        {item.start_date ? <Text style={styles.meta}>📅 {item.start_date}</Text> : null}
        {item.nb_days ? <Text style={styles.meta}>🗓 {item.nb_days} jours</Text> : null}
        {isShared && item.owner_username ? <Text style={styles.meta}>👤 {item.owner_username}</Text> : null}
      </View>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>{t('trips.title')}</Text>
        <TouchableOpacity style={styles.addBtn} onPress={() => navigation.navigate('RoadTripCreate')}>
          <Text style={styles.addBtnText}>+ {t('trips.create')}</Text>
        </TouchableOpacity>
      </View>

      <FlatList
        data={[...myTrips.map(t => ({...t, _shared: false})), ...sharedTrips.map(t => ({...t, _shared: true}))]}
        keyExtractor={item => `${(item as any)._shared ? 's' : 'm'}-${item.id}`}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(true); }} />}
        ListHeaderComponent={myTrips.length === 0 && sharedTrips.length === 0 ? null : (
          <>
            {myTrips.length > 0 && <Text style={styles.sectionLabel}>{t('trips.myTrips')}</Text>}
          </>
        )}
        ListEmptyComponent={<Text style={styles.empty}>{t('trips.empty')}</Text>}
        renderItem={({item}) => <TripCard item={item} isShared={(item as any)._shared} />}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#f5f5f5'},
  centered: {flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24},
  loginMsg: {fontSize: 16, color: '#555', marginBottom: 16, textAlign: 'center'},
  loginBtn: {backgroundColor: PRIMARY, borderRadius: 8, paddingHorizontal: 24, paddingVertical: 12},
  loginBtnText: {color: '#fff', fontWeight: 'bold', fontSize: 16},
  header: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 16, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#eee'},
  headerTitle: {fontSize: 20, fontWeight: 'bold', color: '#333'},
  addBtn: {backgroundColor: PRIMARY, borderRadius: 8, paddingHorizontal: 12, paddingVertical: 6},
  addBtnText: {color: '#fff', fontWeight: 'bold', fontSize: 14},
  sectionLabel: {fontSize: 14, fontWeight: '600', color: '#888', paddingHorizontal: 16, paddingTop: 12, paddingBottom: 4, textTransform: 'uppercase'},
  empty: {textAlign: 'center', color: '#888', marginTop: 60, fontSize: 16},
  card: {backgroundColor: '#fff', margin: 8, borderRadius: 10, padding: 14, elevation: 2},
  cardHeader: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 6},
  cardTitleRow: {flexDirection: 'row', alignItems: 'center', flex: 1, gap: 8},
  cardTitle: {fontSize: 17, fontWeight: '600', color: '#333'},
  sharedBadge: {backgroundColor: '#E3F2FD', borderRadius: 4, paddingHorizontal: 6, paddingVertical: 2},
  sharedBadgeText: {color: '#1565C0', fontSize: 11, fontWeight: '600'},
  deleteBtn: {fontSize: 18},
  desc: {color: '#666', fontSize: 13, marginBottom: 8},
  cardMeta: {flexDirection: 'row', flexWrap: 'wrap', gap: 8},
  meta: {color: '#555', fontSize: 13},
});
