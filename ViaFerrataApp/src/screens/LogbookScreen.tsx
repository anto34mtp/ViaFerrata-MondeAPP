import React, {useState, useCallback} from 'react';
import {
  View, Text, FlatList, TouchableOpacity, StyleSheet,
  Modal, TextInput, Alert, RefreshControl, ScrollView,
} from 'react-native';
import {useFocusEffect} from '@react-navigation/native';
import {getLogbook, addLogbookEntry, deleteLogbookEntry, LogbookEntry} from '../api/client';
import {useLang} from '../context/LangContext';
import LoadingScreen from '../components/LoadingScreen';

const PRIMARY = '#2E7D32';

export default function LogbookScreen() {
  const {t} = useLang();
  const [entries, setEntries] = useState<LogbookEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [form, setForm] = useState({via_id: '', done_date: '', conditions: '', companion: '', notes: ''});
  const [saving, setSaving] = useState(false);

  const load = useCallback(async (silent = false) => {
    if (!silent) setLoading(true);
    try {
      const res = await getLogbook();
      setEntries((res.data as any)?.data ?? res.data ?? []);
    } catch {
      // ignore
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const handleDelete = (id: number) => {
    Alert.alert(t('logbook.delete'), t('logbook.confirmDelete'), [
      {text: t('common.cancel'), style: 'cancel'},
      {
        text: t('common.delete'), style: 'destructive',
        onPress: async () => {
          try {
            await deleteLogbookEntry(id);
            load(true);
          } catch {
            Alert.alert(t('common.error'));
          }
        },
      },
    ]);
  };

  const handleSave = async () => {
    if (!form.via_id || !form.done_date) {
      Alert.alert(t('common.error'), t('logbook.requiredFields'));
      return;
    }
    setSaving(true);
    try {
      await addLogbookEntry({
        via_id: parseInt(form.via_id, 10),
        done_date: form.done_date,
        conditions: form.conditions,
        companion: form.companion,
        notes: form.notes,
      });
      setModalVisible(false);
      setForm({via_id: '', done_date: '', conditions: '', companion: '', notes: ''});
      load(true);
    } catch {
      Alert.alert(t('common.error'));
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <LoadingScreen />;

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>{t('logbook.title')}</Text>
        <TouchableOpacity style={styles.addBtn} onPress={() => setModalVisible(true)}>
          <Text style={styles.addBtnText}>+ {t('logbook.add')}</Text>
        </TouchableOpacity>
      </View>

      <FlatList
        data={entries}
        keyExtractor={item => String(item.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(true); }} />}
        ListEmptyComponent={<Text style={styles.empty}>{t('logbook.empty')}</Text>}
        renderItem={({item}) => (
          <View style={styles.card}>
            <View style={styles.cardHeader}>
              <Text style={styles.viaName}>{(item as any).via_name ?? `Via #${item.via_id}`}</Text>
              <TouchableOpacity onPress={() => handleDelete(item.id)}>
                <Text style={styles.deleteBtn}>🗑</Text>
              </TouchableOpacity>
            </View>
            <Text style={styles.date}>📅 {item.done_date}</Text>
            {item.conditions ? <Text style={styles.meta}>🌤 {item.conditions}</Text> : null}
            {item.companion ? <Text style={styles.meta}>👥 {item.companion}</Text> : null}
            {item.notes ? <Text style={styles.notes}>{item.notes}</Text> : null}
          </View>
        )}
      />

      <Modal visible={modalVisible} animationType="slide" transparent>
        <View style={styles.modalOverlay}>
          <ScrollView style={styles.modalContent}>
            <Text style={styles.modalTitle}>{t('logbook.addEntry')}</Text>

            <Text style={styles.label}>{t('logbook.viaId')} *</Text>
            <TextInput style={styles.input} keyboardType="numeric"
              value={form.via_id} onChangeText={v => setForm(f => ({...f, via_id: v}))} />

            <Text style={styles.label}>{t('logbook.date')} * (AAAA-MM-JJ)</Text>
            <TextInput style={styles.input} placeholder="2024-06-15"
              value={form.done_date} onChangeText={v => setForm(f => ({...f, done_date: v}))} />

            <Text style={styles.label}>{t('logbook.conditions')}</Text>
            <TextInput style={styles.input} placeholder="Beau temps, voie sèche"
              value={form.conditions} onChangeText={v => setForm(f => ({...f, conditions: v}))} />

            <Text style={styles.label}>{t('logbook.companion')}</Text>
            <TextInput style={styles.input} placeholder="Alice, Bob"
              value={form.companion} onChangeText={v => setForm(f => ({...f, companion: v}))} />

            <Text style={styles.label}>{t('logbook.notes')}</Text>
            <TextInput style={[styles.input, styles.textarea]} multiline numberOfLines={4}
              value={form.notes} onChangeText={v => setForm(f => ({...f, notes: v}))} />

            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.cancelBtn} onPress={() => setModalVisible(false)}>
                <Text style={styles.cancelBtnText}>{t('common.cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.saveBtn} onPress={handleSave} disabled={saving}>
                <Text style={styles.saveBtnText}>{saving ? '...' : t('common.save')}</Text>
              </TouchableOpacity>
            </View>
          </ScrollView>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: '#f5f5f5'},
  header: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', padding: 16, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#eee'},
  headerTitle: {fontSize: 20, fontWeight: 'bold', color: '#333'},
  addBtn: {backgroundColor: PRIMARY, borderRadius: 8, paddingHorizontal: 12, paddingVertical: 6},
  addBtnText: {color: '#fff', fontWeight: 'bold', fontSize: 14},
  empty: {textAlign: 'center', color: '#888', marginTop: 60, fontSize: 16},
  card: {backgroundColor: '#fff', margin: 8, borderRadius: 10, padding: 14, elevation: 2},
  cardHeader: {flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6},
  viaName: {fontSize: 16, fontWeight: '600', color: '#333', flex: 1},
  deleteBtn: {fontSize: 18},
  date: {color: PRIMARY, fontWeight: '500', marginBottom: 4},
  meta: {color: '#555', fontSize: 13, marginBottom: 2},
  notes: {color: '#444', fontSize: 13, marginTop: 6, fontStyle: 'italic'},
  modalOverlay: {flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'flex-end'},
  modalContent: {backgroundColor: '#fff', borderTopLeftRadius: 20, borderTopRightRadius: 20, padding: 20, maxHeight: '90%'},
  modalTitle: {fontSize: 20, fontWeight: 'bold', color: '#333', marginBottom: 16},
  label: {fontSize: 13, color: '#555', marginBottom: 4, marginTop: 8},
  input: {borderWidth: 1, borderColor: '#ddd', borderRadius: 8, padding: 10, fontSize: 15, backgroundColor: '#fafafa'},
  textarea: {height: 80, textAlignVertical: 'top'},
  modalActions: {flexDirection: 'row', gap: 12, marginTop: 20, marginBottom: 40},
  cancelBtn: {flex: 1, borderWidth: 1, borderColor: '#ddd', borderRadius: 8, padding: 12, alignItems: 'center'},
  cancelBtnText: {color: '#555', fontWeight: '500'},
  saveBtn: {flex: 1, backgroundColor: PRIMARY, borderRadius: 8, padding: 12, alignItems: 'center'},
  saveBtnText: {color: '#fff', fontWeight: 'bold'},
});
