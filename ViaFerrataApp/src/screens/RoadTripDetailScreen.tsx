import React, {useState, useCallback} from 'react';
import {
  View, Text, ScrollView, TouchableOpacity, StyleSheet,
  Alert, Modal, TextInput, Linking, Platform,
} from 'react-native';
import {useFocusEffect, useRoute, useNavigation} from '@react-navigation/native';
import {NativeStackNavigationProp, NativeStackRouteProp} from '@react-navigation/native-stack';
import {getTripDetail, addViaToTrip, removeViaFromTrip, Trip} from '../api/client';
import {useLang} from '../context/LangContext';
import {useAuth} from '../context/AuthContext';
import LoadingScreen from '../components/LoadingScreen';
import DifficultyBadge from '../components/DifficultyBadge';
import {RootStackParamList} from '../navigation/AppNavigator';

const PRIMARY = '#2E7D32';

function openNavigation(lat: number, lng: number, name: string) {
  const label = encodeURIComponent(name);
  const url = Platform.OS === 'ios'
    ? `maps://?daddr=${lat},${lng}&q=${label}`
    : `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
  Linking.openURL(url).catch(() =>
    Linking.openURL(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`),
  );
}
type Route = NativeStackRouteProp<RootStackParamList, 'RoadTripDetail'>;
type Nav = NativeStackNavigationProp<RootStackParamList>;

export default function RoadTripDetailScreen() {
  const {t} = useLang();
  const {user} = useAuth();
  const route = useRoute<Route>();
  const navigation = useNavigation<Nav>();
  const {id} = route.params;

  const [trip, setTrip] = useState<Trip | null>(null);
  const [loading, setLoading] = useState(true);
  const [addModal, setAddModal] = useState(false);
  const [addForm, setAddForm] = useState({via_id: '', day_number: '1', notes: ''});
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await getTripDetail(id);
      setTrip((res.data as any)?.data ?? res.data);
    } catch {
      Alert.alert(t('common.error'), t('trips.loadError'));
    } finally {
      setLoading(false);
    }
  }, [id, t]);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  if (loading) return <LoadingScreen />;
  if (!trip) return <View style={styles.centered}><Text>{t('common.notFound')}</Text></View>;

  const isOwner = (trip as any).is_owner;
  const viasByDay: Record<string, any[]> = (trip as any).vias_by_day ?? {};
  const days = Object.keys(viasByDay).sort((a, b) => Number(a) - Number(b));

  const handleAddVia = async () => {
    if (!addForm.via_id) return;
    setSaving(true);
    try {
      await addViaToTrip(id, {
        via_id: parseInt(addForm.via_id, 10),
        day_number: parseInt(addForm.day_number, 10) || 1,
        notes: addForm.notes,
      });
      setAddModal(false);
      setAddForm({via_id: '', day_number: '1', notes: ''});
      load();
    } catch {
      Alert.alert(t('common.error'));
    } finally {
      setSaving(false);
    }
  };

  const handleRemoveVia = (viaId: number, viaName: string) => {
    Alert.alert(t('trips.removeVia'), `Retirer "${viaName}" du trip ?`, [
      {text: t('common.cancel'), style: 'cancel'},
      {
        text: t('common.remove'), style: 'destructive',
        onPress: async () => {
          try {
            await removeViaFromTrip(id, viaId);
            load();
          } catch {
            Alert.alert(t('common.error'));
          }
        },
      },
    ]);
  };

  return (
    <View style={styles.container}>
      <ScrollView>
        {/* Trip header */}
        <View style={styles.tripHeader}>
          <Text style={styles.tripName}>{trip.name}</Text>
          {trip.description ? <Text style={styles.tripDesc}>{trip.description}</Text> : null}
          <View style={styles.tripMeta}>
            {trip.start_date ? <Text style={styles.metaItem}>📅 {trip.start_date}</Text> : null}
            {trip.end_date ? <Text style={styles.metaItem}>→ {trip.end_date}</Text> : null}
            {trip.nb_days ? <Text style={styles.metaItem}>🗓 {trip.nb_days} jours</Text> : null}
          </View>
        </View>

        {/* Days */}
        {days.length === 0 ? (
          <Text style={styles.empty}>{t('trips.noVias')}</Text>
        ) : (
          days.map(day => (
            <View key={day} style={styles.daySection}>
              <Text style={styles.dayTitle}>Jour {day}</Text>
              {viasByDay[day].map((via: any) => (
                <View key={via.id} style={styles.viaCard}>
                  <TouchableOpacity
                    style={styles.viaInfo}
                    onPress={() => navigation.navigate('ViaDetail', {slug: via.slug})}>
                    <Text style={styles.viaName}>{via.name}</Text>
                    {via.location ? <Text style={styles.viaLocation}>📍 {via.location}</Text> : null}
                    {via.difficulty ? <DifficultyBadge level={via.difficulty} size="small" /> : null}
                    {via.notes ? <Text style={styles.viaNotes}>{via.notes}</Text> : null}
                  </TouchableOpacity>
                  <View style={styles.viaActions}>
                    {(via.gps_lat && via.gps_lng) ? (
                      <TouchableOpacity
                        style={styles.navBtn}
                        onPress={() => openNavigation(via.gps_lat, via.gps_lng, via.name)}>
                        <Text style={styles.navBtnText}>🧭</Text>
                      </TouchableOpacity>
                    ) : null}
                    {isOwner && (
                      <TouchableOpacity onPress={() => handleRemoveVia(via.id, via.name)}>
                        <Text style={styles.removeBtn}>✕</Text>
                      </TouchableOpacity>
                    )}
                  </View>
                </View>
              ))}
            </View>
          ))
        )}
      </ScrollView>

      {/* FAB add via */}
      {isOwner && (
        <TouchableOpacity style={styles.fab} onPress={() => setAddModal(true)}>
          <Text style={styles.fabText}>＋</Text>
        </TouchableOpacity>
      )}

      {/* Add via modal */}
      <Modal visible={addModal} animationType="slide" transparent>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>{t('trips.addVia')}</Text>

            <Text style={styles.label}>ID de la via *</Text>
            <TextInput style={styles.input} keyboardType="numeric" placeholder="Ex: 42"
              placeholderTextColor="#999"
              value={addForm.via_id} onChangeText={v => setAddForm(f => ({...f, via_id: v}))} />

            <Text style={styles.label}>Jour *</Text>
            <TextInput style={styles.input} keyboardType="numeric" placeholder="1"
              placeholderTextColor="#999"
              value={addForm.day_number} onChangeText={v => setAddForm(f => ({...f, day_number: v}))} />

            <Text style={styles.label}>Notes (optionnel)</Text>
            <TextInput style={[styles.input, styles.textarea]} multiline
              placeholderTextColor="#999"
              value={addForm.notes} onChangeText={v => setAddForm(f => ({...f, notes: v}))} />

            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.cancelBtn} onPress={() => setAddModal(false)}>
                <Text style={styles.cancelBtnText}>{t('common.cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.saveBtn} onPress={handleAddVia} disabled={saving}>
                <Text style={styles.saveBtnText}>{saving ? '...' : t('common.add')}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#f5f5f5'},
  centered: {flex: 1, justifyContent: 'center', alignItems: 'center'},
  tripHeader: {backgroundColor: '#fff', padding: 16, marginBottom: 8, borderBottomWidth: 1, borderBottomColor: '#eee'},
  tripName: {fontSize: 22, fontWeight: 'bold', color: '#333', marginBottom: 6},
  tripDesc: {color: '#666', fontSize: 14, marginBottom: 8},
  tripMeta: {flexDirection: 'row', gap: 12, flexWrap: 'wrap'},
  metaItem: {color: '#555', fontSize: 13},
  empty: {textAlign: 'center', color: '#888', marginTop: 40, fontSize: 16},
  daySection: {backgroundColor: '#fff', margin: 8, borderRadius: 10, overflow: 'hidden', elevation: 2},
  dayTitle: {backgroundColor: PRIMARY, color: '#fff', fontWeight: 'bold', fontSize: 15, padding: 10},
  viaCard: {flexDirection: 'row', alignItems: 'center', padding: 12, borderBottomWidth: 1, borderBottomColor: '#f0f0f0'},
  viaInfo: {flex: 1},
  viaActions: {flexDirection: 'row', alignItems: 'center', gap: 8},
  navBtn: {backgroundColor: '#1565C0', borderRadius: 8, paddingVertical: 6, paddingHorizontal: 8},
  navBtnText: {fontSize: 16},
  viaName: {fontSize: 15, fontWeight: '600', color: '#333', marginBottom: 2},
  viaLocation: {color: '#666', fontSize: 13, marginBottom: 4},
  viaNotes: {color: '#888', fontSize: 12, fontStyle: 'italic', marginTop: 4},
  removeBtn: {fontSize: 18, color: '#f44336', paddingLeft: 8},
  fab: {position: 'absolute', bottom: 24, right: 24, width: 56, height: 56, borderRadius: 28, backgroundColor: PRIMARY, justifyContent: 'center', alignItems: 'center', elevation: 6},
  fabText: {color: '#fff', fontSize: 28, lineHeight: 32},
  modalOverlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end'},
  modalContent: {backgroundColor: '#fff', borderTopLeftRadius: 20, borderTopRightRadius: 20, padding: 20},
  modalTitle: {fontSize: 20, fontWeight: 'bold', color: '#333', marginBottom: 16},
  label: {fontSize: 13, color: '#555', marginBottom: 4, marginTop: 8},
  input: {borderWidth: 1, borderColor: '#ddd', borderRadius: 8, padding: 10, fontSize: 15, color: '#1A1A1A', backgroundColor: '#fff'},
  textarea: {height: 70, textAlignVertical: 'top'},
  modalActions: {flexDirection: 'row', gap: 12, marginTop: 16, marginBottom: 20},
  cancelBtn: {flex: 1, borderWidth: 1, borderColor: '#ddd', borderRadius: 8, padding: 12, alignItems: 'center'},
  cancelBtnText: {color: '#555', fontWeight: '500'},
  saveBtn: {flex: 1, backgroundColor: PRIMARY, borderRadius: 8, padding: 12, alignItems: 'center'},
  saveBtnText: {color: '#fff', fontWeight: 'bold'},
});
